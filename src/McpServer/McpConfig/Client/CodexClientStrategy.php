<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\McpConfig\Client;

use Butschster\ContextGenerator\McpServer\McpConfig\Model\McpConfig;
use Butschster\ContextGenerator\McpServer\McpConfig\Model\OsInfo;
use Butschster\ContextGenerator\McpServer\McpConfig\Renderer\McpConfigRenderer;
use Symfony\Component\Console\Style\SymfonyStyle;

final class CodexClientStrategy extends AbstractClientStrategy
{
    public function getKey(): string
    {
        return 'codex';
    }

    public function getLabel(): string
    {
        return 'Codex';
    }

    public function getGeneratorClientKey(): string
    {
        // Use generic generator format and print TOML for Codex
        return 'generic';
    }

    #[\Override]
    public function renderConfiguration(
        McpConfigRenderer $renderer,
        McpConfig $config,
        OsInfo $osInfo,
        array $options,
        SymfonyStyle $output,
    ): void {
        // Render Codex TOML snippet
        $output->section('Generated Configuration');
        $output->text('Codex configuration (TOML):');
        $output->newLine();

        $args = \array_map(static fn(string $arg): string =>
            // Basic escaping for double quotes
            '"' . \str_replace('"', '\\"', $arg) . '"', $config->args);

        $toml = "[mcp_servers.ctx]\n"
            . "command = \"{$config->command}\"\n"
            . 'args = [' . \implode(', ', $args) . "]\n";

        $output->writeln('<comment>' . $toml . '</comment>');
        $output->newLine();
    }

    #[\Override]
    public function renderExplanation(
        McpConfigRenderer $renderer,
        McpConfig $config,
        OsInfo $osInfo,
        array $options,
        SymfonyStyle $output,
    ): void {
        $output->section('Setup Instructions');

        $steps = [
            '1. Copy the TOML snippet above to your Codex MCP client configuration.',
            '2. Ensure the `ctx` binary is available in your PATH.',
            '3. Restart the Codex client and verify the MCP server connection.',
        ];

        foreach ($steps as $step) {
            $output->text('   ' . $step);
        }

        $output->newLine();
    }
}
