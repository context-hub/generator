<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\McpConfig\Client;

final class GenericClientStrategy extends AbstractClientStrategy
{
    public function getKey(): string
    {
        return 'generic';
    }

    public function getLabel(): string
    {
        return 'Generic MCP Client';
    }

    public function getGeneratorClientKey(): string
    {
        return 'generic';
    }
}
