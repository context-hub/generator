<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Console;

use Butschster\ContextGenerator\Console\BaseCommand;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\McpConfig\ConfigGeneratorInterface;
use Butschster\ContextGenerator\McpServer\McpConfig\Renderer\McpConfigRenderer;
use Butschster\ContextGenerator\McpServer\McpConfig\Service\OsDetectionService;
use Butschster\ContextGenerator\McpServer\McpConfig\Client\ClientStrategyRegistry;
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
        name: 'interactive',
        shortcut: 'i',
        description: 'Interactive mode with guided questions',
    )]
    protected bool $interactive = false;

    #[Option(
        name: 'client',
        shortcut: 'c',
        description: 'MCP client type (claude, codex, cursor, generic)',
    )]
    protected string $client = 'claude';

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

    public function __invoke(
        OsDetectionService $osDetection,
        ConfigGeneratorInterface $configGenerator,
        DirectoriesInterface $dirs,
    ): int {
        $renderer = new McpConfigRenderer($this->output);
        $renderer->renderHeader();

        // Handle interactive mode
        if ($this->interactive) {
            return $this->runInteractiveMode($osDetection, $configGenerator, $renderer, $dirs);
        }

        // Detect operating system and environment
        $osInfo = $osDetection->detect($this->forceWsl);

        // Determine configuration approach
        $options = $this->buildConfigOptions($dirs);

        // Resolve selected client strategy
        $registry = new ClientStrategyRegistry();
        $strategy = $registry->getByKey($this->client) ?? $registry->getDefault();

        // Generate configuration via vendor generator (supports claude/generic)
        $config = $configGenerator->generate(
            client: $strategy->getGeneratorClientKey(),
            osInfo: $osInfo,
            projectPath: $options['project_path'] ?? (string) $dirs->getRootPath(),
            options: $options,
        );

        // Render using strategy
        $strategy->renderConfiguration($renderer, $config, $osInfo, $options, $this->output);
        if ($this->explain) {
            $strategy->renderExplanation($renderer, $config, $osInfo, $options, $this->output);
        }

        return Command::SUCCESS;
    }

    private function runInteractiveMode(
        OsDetectionService $osDetection,
        ConfigGeneratorInterface $configGenerator,
        McpConfigRenderer $renderer,
        DirectoriesInterface $dirs,
    ): int {
        $renderer->renderInteractiveWelcome();

        // Ask about client type
        $registry = new ClientStrategyRegistry();
        $choice = $this->output->choice(
            'Which MCP client are you configuring?',
            $registry->getChoiceLabels(),
            $registry->getDefault()->getLabel(),
        );
        $strategy = $registry->getByLabel($choice) ?? $registry->getDefault();

        // Auto-detect OS
        $osInfo = $osDetection->detect();
        $renderer->renderDetectedEnvironment($osInfo);

        // Ask about WSL if on Windows
        if ($osInfo->isWindows() && !$osInfo->isWsl()) {
            $useWsl = $this->output->confirm(
                'Are you planning to run CTX inside WSL (Windows Subsystem for Linux)?',
                false,
            );

            if ($useWsl) {
                $osInfo = $osDetection->detect(forceWsl: true);
            }
        }

        // Ask about project configuration approach
        $configChoice = $this->output->choice(
            'How do you want to configure project access?',
            [
                'global' => 'Use global project registry (switch between projects dynamically)',
                'specific' => 'Use specific project path (single project)',
            ],
            'global',
        );

        $options = ['use_project_path' => false];
        $projectPath = (string) $dirs->getRootPath();

        if ($configChoice === 'specific') {
            $options['use_project_path'] = true;

            // Ask about project path
            $defaultPath = (string) $dirs->getRootPath();
            $projectPath = $this->output->ask(
                'What is the path to your CTX project?',
                $defaultPath,
            );

            // Validate project path
            if (!\is_dir($projectPath)) {
                $this->output->warning("Warning: The specified path does not exist: {$projectPath}");

                if (!$this->output->confirm('Continue anyway?', true)) {
                    return Command::FAILURE;
                }
            }
        }

        // Ask about environment variables
        if ($this->output->confirm('Do you need to configure environment variables (e.g., GitHub token)?', false)) {
            $options['enable_file_operations'] = $this->output->confirm('Enable file operations?', true);

            if ($this->output->confirm('Do you have a GitHub personal access token?', false)) {
                $options['github_token'] = $this->output->askHidden('GitHub Token (input will be hidden):');
            }
        }

        // Generate and display configuration
        $config = $configGenerator->generate(
            client: $strategy->getGeneratorClientKey(),
            osInfo: $osInfo,
            projectPath: $projectPath,
            options: $options,
        );

        $strategy->renderConfiguration($renderer, $config, $osInfo, $options, $this->output);
        $strategy->renderExplanation($renderer, $config, $osInfo, $options, $this->output);

        return Command::SUCCESS;
    }

    private function buildConfigOptions(DirectoriesInterface $dirs): array
    {
        $options = [];

        // Determine if we should use project path
        if ($this->projectPath !== null) {
            $options['use_project_path'] = true;
            $options['project_path'] = $this->projectPath;
        } elseif (!$this->useGlobal) {
            // If not explicitly global and we have a project path, use it
            $options['use_project_path'] = true;
            $options['project_path'] = (string) $dirs->getRootPath();
        } else {
            $options['use_project_path'] = false;
        }

        return $options;
    }
}
