<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\McpConfig\Client;

final class ClaudeDesktopClientStrategy extends AbstractClientStrategy
{
    public function getKey(): string
    {
        return 'claude';
    }

    public function getLabel(): string
    {
        return 'Claude Desktop';
    }

    public function getGeneratorClientKey(): string
    {
        return 'claude';
    }
}

