<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool;

use Butschster\ContextGenerator\Config\Registry\RegistryInterface;
use Butschster\ContextGenerator\McpServer\Tool\Config\ToolDefinition;
use Spiral\Core\Attribute\Singleton;

/**
 * Registry for storing tool definitions.
 * @template TTool of ToolDefinition
 * @implements RegistryInterface<TTool>
 */
#[Singleton]
final class ToolRegistry implements RegistryInterface, ToolProviderInterface, ToolRegistryInterface
{
    /** @var array<non-empty-string, TTool> */
    private array $tools = [];

    public function register(ToolDefinition $tool): void
    {
        /**
         * @psalm-suppress InvalidPropertyAssignmentValue
         */
        $this->tools[$tool->id] = $tool;
    }

    public function get(string $id): ToolDefinition
    {
        if (!$this->has($id)) {
            throw new \InvalidArgumentException(
                \sprintf('No tool with the ID "%s" exists', $id),
            );
        }

        return $this->tools[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->tools[$id]);
    }

    public function all(): array
    {
        return $this->getItems();
    }

    public function getType(): string
    {
        return 'tools';
    }

    public function getItems(): array
    {
        return \array_values($this->tools);
    }

    public function jsonSerialize(): array
    {
        return [
            'tools' => $this->getItems(),
        ];
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->getItems());
    }
}
