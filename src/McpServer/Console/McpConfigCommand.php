<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Console;

use Butschster\ContextGenerator\Console\BaseCommand;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Console\McpConfig\ConfigGeneratorInterface;
use Butschster\ContextGenerator\McpServer\Console\McpConfig\Renderer\McpConfigRenderer;
use Butschster\ContextGenerator\McpServer\Console\McpConfig\Service\OsDetectionService;
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
        description: 'Force WSL configuration mode',
    )]
    protected bool $forceWsl = false;

    #[Option(
        name: 'explain',
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
        description: 'MCP client type (claude, generic)',
    )]
    protected string $client = 'claude';

    #[Option(
        name: 'project-path',
        shortcut: 'p',
        description: 'Override project path in configuration',
    )]
    protected ?string $projectPath = null;

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

        $projectPath = $this->projectPath ?? (string) $dirs->getRootPath();

        // Generate configuration
        $config = $configGenerator->generate(
            client: $this->client,
            osInfo: $osInfo,
            projectPath: $projectPath,
        );

        // Render the configuration
        $renderer->renderConfiguration($config, $osInfo);

        // Show explanations if requested
        if ($this->explain) {
            $renderer->renderExplanation($config, $osInfo);
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
        $clientType = $this->output->choice(
            'Which MCP client are you configuring?',
            ['claude' => 'Claude Desktop', 'generic' => 'Generic MCP Client'],
            'claude',
        );

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

        // Ask about project path
        $defaultPath = (string) $dirs->getRootPath();
        $projectPath = $this->output->ask(
            'What is the path to your CTX project?',
            $defaultPath,
        );

        // Validate project path
        if (!$dirs->getRootPath()->join($projectPath)->exists()) {
            $this->output->warning("Warning: The specified path does not exist: {$projectPath}");

            if (!$this->output->confirm('Continue anyway?', true)) {
                return Command::FAILURE;
            }
        }

        // Generate and display configuration
        $config = $configGenerator->generate(
            client: $clientType,
            osInfo: $osInfo,
            projectPath: $projectPath,
        );

        $renderer->renderConfiguration($config, $osInfo);
        $renderer->renderExplanation($config, $osInfo);

        return Command::SUCCESS;
    }
}
