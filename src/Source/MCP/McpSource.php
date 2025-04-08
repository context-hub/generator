<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\MCP;

use Butschster\ContextGenerator\Source\SourceWithModifiers;
use Butschster\ContextGenerator\Source\MCP\Config\ServerConfig;
use Butschster\ContextGenerator\Source\MCP\Operations\OperationInterface;

/**
 * Source for MCP (Model Context Protocol) server content
 */
final class McpSource extends SourceWithModifiers
{
    /**
     * @param ServerConfig $serverConfig Server configuration
     * @param OperationInterface $operation Operation to execute on the server
     * @param string $description Human-readable description
     * @param array<non-empty-string> $tags
     * @param array<\Butschster\ContextGenerator\Modifier\Modifier> $modifiers
     */
    public function __construct(
        public readonly ServerConfig $serverConfig,
        public readonly OperationInterface $operation,
        string $description = '',
        array $tags = [],
        array $modifiers = [],
    ) {
        parent::__construct(description: $description, tags: $tags, modifiers: $modifiers);
    }

    #[\Override]
    public function jsonSerialize(): array
    {
        return \array_filter([
            'type' => 'mcp',
            ...parent::jsonSerialize(),
            'server' => $this->serverConfig,
            'operation' => $this->operation,
        ], static fn($value) => $value !== null && $value !== '' && $value !== []);
    }
}
