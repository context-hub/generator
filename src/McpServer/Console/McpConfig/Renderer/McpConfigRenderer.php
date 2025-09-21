<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Console\McpConfig\Renderer;

use Butschster\ContextGenerator\McpServer\Console\McpConfig\Model\McpConfig;
use Butschster\ContextGenerator\McpServer\Console\McpConfig\Model\OsInfo;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class McpConfigRenderer
{
    public function __construct(
        private SymfonyStyle $output,
    ) {}

    public function renderHeader(): void
    {
        $this->output->title('MCP Configuration Generator');
        $this->output->text([
            'This tool generates configuration snippets for connecting CTX to MCP clients like Claude Desktop.',
            'It automatically detects your operating system and generates the appropriate configuration format.',
        ]);
        $this->output->newLine();
    }

    public function renderInteractiveWelcome(): void
    {
        $this->output->section('Interactive Configuration Mode');
        $this->output->text('Let\'s configure your MCP client step by step...');
        $this->output->newLine();
    }

    public function renderDetectedEnvironment(OsInfo $osInfo): void
    {
        $this->output->section('Environment Detection');

        $this->output->definitionList(
            ['Operating System' => $osInfo->getDisplayName()],
            ['PHP OS' => $osInfo->phpOs],
            ['Architecture' => $osInfo->additionalInfo['architecture'] ?? 'Unknown'],
        );

        if ($osInfo->isWsl) {
            $this->output->note('WSL environment detected. Configuration will use bash.exe wrapper.');
        }

        $this->output->newLine();
    }

    public function renderConfiguration(McpConfig $config, OsInfo $osInfo, array $options = []): void
    {
        $this->output->section('Generated Configuration');

        $configType = ($options['use_project_path'] ?? false) ? 'Project-specific' : 'Global project registry';

        $this->output->text([
            "Configuration type: <info>{$config->clientType}</info>",
            "Operating system: <info>{$osInfo->getDisplayName()}</info>",
            "Project mode: <info>{$configType}</info>",
            "Command: <info>{$config->getDisplayCommand()}</info>",
        ]);

        $this->output->newLine();

        if ($config->clientType === 'claude') {
            $this->renderClaudeConfig($config, $osInfo);
        } else {
            $this->renderGenericConfig($config, $osInfo);
        }
    }

    public function renderExplanation(McpConfig $config, OsInfo $osInfo, array $options = []): void
    {
        $this->output->section('Setup Instructions');

        if ($config->clientType === 'claude') {
            $this->renderClaudeSetupInstructions($config, $osInfo, $options);
        } else {
            $this->renderGenericSetupInstructions($config, $osInfo, $options);
        }

        $this->renderTroubleshootingTips($osInfo);
    }

    private function renderClaudeConfig(McpConfig $config, OsInfo $osInfo): void
    {
        $this->output->text('Add this configuration to your Claude Desktop config file:');
        $this->output->newLine();

        $this->output->writeln('<comment>' . $config->toJson() . '</comment>');
        $this->output->newLine();

        // Show config file location hints
        $this->renderClaudeConfigLocation($osInfo);
    }

    private function renderGenericConfig(McpConfig $config, OsInfo $osInfo): void
    {
        $this->output->text('Generic MCP client configuration:');
        $this->output->newLine();

        $this->output->writeln('<comment>' . $config->toJson() . '</comment>');
        $this->output->newLine();
    }

    private function renderClaudeConfigLocation(OsInfo $osInfo): void
    {
        $this->output->text('Claude Desktop configuration file location:');

        $configPaths = match (true) {
            $osInfo->isWindows || $osInfo->isWsl => [
                '%APPDATA%\\Claude\\claude_desktop_config.json',
                'C:\\Users\\<username>\\AppData\\Roaming\\Claude\\claude_desktop_config.json',
            ],
            $osInfo->isMacOs => [
                '~/Library/Application Support/Claude/claude_desktop_config.json',
            ],
            default => [
                '~/.config/Claude/claude_desktop_config.json',
                '$XDG_CONFIG_HOME/Claude/claude_desktop_config.json',
            ],
        };

        foreach ($configPaths as $path) {
            $this->output->text("  • <info>{$path}</info>");
        }

        $this->output->newLine();
    }

    private function renderClaudeSetupInstructions(McpConfig $config, OsInfo $osInfo, array $options = []): void
    {
        $this->output->text('To set up Claude Desktop with CTX:');

        $steps = [
            '1. Close Claude Desktop if it\'s running',
            '2. Open the Claude Desktop configuration file (see paths above)',
            '3. If the file doesn\'t exist, create it with the generated configuration',
            '4. If the file exists, merge the "mcpServers" section with your existing configuration',
            '5. Save the file and restart Claude Desktop',
            '6. You should see CTX listed in the MCP servers when you start a new conversation',
        ];

        foreach ($steps as $step) {
            $this->output->text("   {$step}");
        }

        $this->output->newLine();

        // Add explanation about configuration mode
        if (isset($options['use_project_path']) && $options['use_project_path']) {
            $this->output->note([
                'Project-specific configuration:',
                '• This configuration is tied to a specific project path',
                '• CTX will only have access to the specified project',
                '• Good for single-project workflows',
            ]);
        } else {
            $this->output->note([
                'Global project registry configuration:',
                '• This configuration uses CTX\'s project registry system',
                '• You can switch between different registered projects dynamically',
                '• Use "ctx project:add" to register projects first',
                '• Good for multi-project workflows',
            ]);
        }

        if ($config->hasEnvironmentVariables()) {
            $this->output->note([
                'This configuration includes environment variables.',
                'Make sure the specified environment variables are available in your system.',
            ]);
        }

        if ($osInfo->isWsl) {
            $this->output->warning([
                'WSL Configuration Notes:',
                '• Make sure CTX is installed and available in your WSL environment',
                '• The path should be a WSL path (e.g., /home/user/project), not a Windows path',
                '• Environment variables are exported within the bash command',
            ]);
        }
    }

    private function renderGenericSetupInstructions(McpConfig $config, OsInfo $osInfo, array $options = []): void
    {
        $this->output->text('To use this configuration with your MCP client:');

        $steps = [
            '1. Refer to your MCP client\'s documentation for configuration format',
            '2. Use the provided command and arguments to configure the server',
            '3. Ensure CTX is installed and available in your system PATH',
            '4. Test the connection by starting your MCP client',
        ];

        foreach ($steps as $step) {
            $this->output->text("   {$step}");
        }

        $this->output->newLine();
    }

    private function renderTroubleshootingTips(OsInfo $osInfo): void
    {
        $this->output->section('Troubleshooting Tips');

        $tips = [
            'If Claude doesn\'t show CTX as available:',
            '  • Check that the configuration file syntax is valid JSON',
            '  • Verify that the CTX binary is installed and accessible',
            '  • Check the Claude Desktop logs for any error messages',
            '  • Try restarting Claude Desktop completely',
        ];

        if ($osInfo->isWindows) {
            $tips = \array_merge($tips, [
                '',
                'Windows-specific tips:',
                '  • Make sure ctx.exe is in your PATH or use the full path in the configuration',
                '  • Use double backslashes (\\\\) in paths if you encounter issues',
            ]);
        }

        if ($osInfo->isWsl) {
            $tips = \array_merge($tips, [
                '',
                'WSL-specific tips:',
                '  • Ensure CTX is installed in your WSL distribution, not just Windows',
                '  • Use WSL paths (/mnt/c/... for Windows drives) in the configuration',
                '  • Test the command manually in WSL: bash.exe -c "ctx server"',
            ]);
        }

        foreach ($tips as $tip) {
            $this->output->text($tip);
        }

        $this->output->newLine();

        $this->output->note([
            'For more help:',
            '• Visit the CTX documentation at https://context-hub.github.io/generator/',
            '• Check the MCP server documentation for troubleshooting guides',
            '• Join the community discussions on GitHub',
        ]);
    }
}
