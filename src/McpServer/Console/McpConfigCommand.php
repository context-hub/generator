<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Console;

use Butschster\ContextGenerator\Console\BaseCommand;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\McpConfig\Client\ClientStrategyRegistry;
use Butschster\ContextGenerator\McpServer\McpConfig\ConfigGeneratorInterface;
use Butschster\ContextGenerator\McpServer\McpConfig\Service\OsDetectionService;
use Spiral\Console\Attribute\Option;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(
    name: 'mcp:config',
    description: 'Generate MCP configuration for connecting CTX to Claude or other MCP clients',
)]
final class McpConfigCommand extends BaseCommand
{
    #[Option(
        name: 'wsl',
        shortcut: 'f',
        description: 'Force WSL configuration mode',
    )]
    protected bool $forceWsl = false;

    #[Option(
        name: 'explain',
        shortcut: 'e',
        description: 'Show detailed setup instructions',
    )]
    protected bool $explain = false;

    #[Option(
        name: 'client',
        shortcut: 'c',
        description: 'MCP client type (claude, codex, cursor, generic)',
    )]
    protected ?string $client = null;

    #[Option(
        name: 'project-path',
        shortcut: 'p',
        description: 'Use specific project path in configuration',
    )]
    protected ?string $projectPath = null;

    #[Option(
        name: 'global',
        shortcut: 'g',
        description: 'Use global project registry (no -c option)',
    )]
    protected bool $useGlobal = true;

    #[Option(
        name: 'sse',
        description: 'Enable SSE (Server-Sent Events) transport mode',
    )]
    protected bool $useSse = false;

    #[Option(
        name: 'host',
        description: 'SSE host to bind to (default: 127.0.0.1)',
    )]
    protected string $sseHost = '127.0.0.1';

    #[Option(
        name: 'port',
        description: 'SSE port to bind to (default: 8080)',
    )]
    protected int $ssePort = 8080;

    public function __invoke(
        OsDetectionService $osDetection,
        ConfigGeneratorInterface $configGenerator,
        DirectoriesInterface $dirs,
        ClientStrategyRegistry $registry,
    ): int {
        $this->output->title('MCP Configuration Generator');

        // Handle interactive mode
        if ($this->client === null) {
            return $this->runInteractiveMode($osDetection, $configGenerator, $registry, $dirs);
        }

        // Detect operating system
        $osInfo = $osDetection->detect($this->forceWsl);

        // Build configuration options
        $options = $this->buildConfigOptions($dirs);

        // Get client strategy
        $strategy = $registry->getByKey($this->client) ?? $registry->getDefault();

        // Generate configuration
        $config = $configGenerator->generate(
            client: $strategy->getGeneratorClientKey(),
            osInfo: $osInfo,
            projectPath: $options['project_path'] ?? (string) $dirs->getRootPath(),
            options: $options,
        );

        // Render using strategy
        $strategy->renderConfiguration($config, $osInfo, $options, $this->output);
        $strategy->renderInstructions($config, $osInfo, $options, $this->output);

        return Command::SUCCESS;
    }

    private function runInteractiveMode(
        OsDetectionService $osDetection,
        ConfigGeneratorInterface $configGenerator,
        ClientStrategyRegistry $registry,
        DirectoriesInterface $dirs,
    ): int {
        $this->output->section('Interactive Configuration');
        $this->output->text('Let\'s configure your MCP client step by step...');
        $this->output->newLine();

        // Ask about client type
        $choice = $this->output->choice(
            'Which MCP client are you configuring?',
            $registry->getChoiceLabels(),
            $registry->getDefault()->getLabel(),
        );

        $strategy = $registry->getByLabel($choice)
            ?? $registry->getByKey(\strtolower(\trim((string) $choice)))
            ?? $registry->getDefault();

        // Detect OS
        $osInfo = $osDetection->detect();

        $this->output->section('Environment');
        $this->output->definitionList(
            ['Operating System' => $osInfo->getDisplayName()],
            ['Architecture' => $osInfo->additionalInfo['architecture'] ?? 'Unknown'],
        );
        $this->output->newLine();

        // Ask about WSL if on Windows
        if ($osInfo->isWindows() && !$osInfo->isWsl()) {
            $useWsl = $this->output->confirm(
                'Are you using WSL (Windows Subsystem for Linux)?',
                false,
            );

            if ($useWsl) {
                $osInfo = $osDetection->detect(forceWsl: true);
            }
        }

        // Ask about project configuration
        $configChoice = $this->output->choice(
            'How do you want to configure project access?',
            [
                'global' => 'Global project registry (switch projects dynamically)',
                'specific' => 'Specific project path (single project)',
            ],
            'global',
        );

        $options = ['use_project_path' => false];
        $projectPath = (string) $dirs->getRootPath();

        if ($configChoice === 'specific') {
            $options['use_project_path'] = true;
            $projectPath = $this->output->ask(
                'Project path:',
                (string) $dirs->getRootPath(),
            );

            if (!\is_dir($projectPath)) {
                $this->output->warning("Path does not exist: {$projectPath}");
                if (!$this->output->confirm('Continue anyway?', true)) {
                    return Command::FAILURE;
                }
            }
        }

        // Ask about SSE mode
        $useSse = $this->output->confirm(
            'Enable SSE (Server-Sent Events) transport mode for remote access?',
            false,
        );

        if ($useSse) {
            $options['use_sse'] = true;
            $options['sse_host'] = $this->output->ask('SSE host:', '127.0.0.1');
            $options['sse_port'] = (int) $this->output->ask('SSE port:', '8080');
        }

        // Generate and display configuration
        $config = $configGenerator->generate(
            client: $strategy->getGeneratorClientKey(),
            osInfo: $osInfo,
            projectPath: $projectPath,
            options: $options,
        );

        $strategy->renderConfiguration($config, $osInfo, $options, $this->output);
        $strategy->renderInstructions($config, $osInfo, $options, $this->output);

        return Command::SUCCESS;
    }

    private function buildConfigOptions(DirectoriesInterface $dirs): array
    {
        $options = [];

        if ($this->projectPath !== null) {
            $options['use_project_path'] = true;
            $options['project_path'] = $this->projectPath;
        } elseif (!$this->useGlobal) {
            $options['use_project_path'] = true;
            $options['project_path'] = (string) $dirs->getRootPath();
        } else {
            $options['use_project_path'] = false;
        }

        // Add SSE options if enabled
        if ($this->useSse) {
            $options['use_sse'] = true;
            $options['sse_host'] = $this->sseHost;
            $options['sse_port'] = $this->ssePort;
        }

        return $options;
    }
}
