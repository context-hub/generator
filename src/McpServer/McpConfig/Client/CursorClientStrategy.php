<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\McpConfig\Client;

use Butschster\ContextGenerator\McpServer\McpConfig\Model\OsInfo;
use Symfony\Component\Console\Style\SymfonyStyle;

final class CursorClientStrategy extends AbstractClientStrategy
{
    public function getKey(): string
    {
        return 'cursor';
    }

    public function getLabel(): string
    {
        return 'Cursor';
    }

    public function getGeneratorClientKey(): string
    {
        // Use generic generator format
        return 'generic';
    }

    #[\Override]
    protected function renderAdditionalNotes(SymfonyStyle $output, OsInfo $osInfo, array $options): void
    {
        $output->note([
            'Cursor setup:',
            '• Use the generated Generic configuration to point Cursor to the CTX MCP server.',
            '• Check Cursor documentation for where to place MCP server settings.',
            '• Ensure the `ctx` command is available on your PATH.',
        ]);
    }
}

