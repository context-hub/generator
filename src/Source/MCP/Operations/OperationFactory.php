<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\MCP\Operations;

/**
 * Factory for creating operations
 */
final readonly class OperationFactory
{
    /**
     * Create an operation from the given configuration
     *
     * @param array<string, mixed> $config Operation configuration
     * @return OperationInterface Created operation
     * @throws \InvalidArgumentException If the operation type is invalid or missing
     */
    public function create(array $config): OperationInterface
    {
        if (!isset($config['type'])) {
            throw new \InvalidArgumentException('Operation configuration must have a "type" property');
        }

        $type = (string) $config['type'];

        return match ($type) {
            'resource.read' => $this->createReadResourceOperation($config),
            'tool.call' => $this->createCallToolOperation($config),
            default => throw new \InvalidArgumentException(\sprintf('Unknown operation type: %s', $type)),
        };
    }

    /**
     * Create a ReadResourceOperation
     */
    private function createReadResourceOperation(array $config): ReadResourceOperation
    {
        if (!isset($config['resources']) || !\is_array($config['resources'])) {
            throw new \InvalidArgumentException('ReadResourceOperation must have a "resources" array property');
        }

        return new ReadResourceOperation($config['resources']);
    }

    /**
     * Create a CallToolOperation
     */
    private function createCallToolOperation(array $config): CallToolOperation
    {
        if (!isset($config['name'])) {
            throw new \InvalidArgumentException('CallToolOperation must have a "name" property');
        }

        return new CallToolOperation(
            name: (string) $config['name'],
            arguments: $config['arguments'] ?? null,
        );
    }
}
