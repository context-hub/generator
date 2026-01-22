<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Tool;

use Spiral\Core\Attribute\Singleton;

/**
 * Registry for RAG tool configurations.
 */
#[Singleton]
final class RagToolRegistry implements RagToolRegistryInterface
{
    /** @var array<string, RagToolConfig> */
    private array $tools = [];

    public function register(RagToolConfig $tool): void
    {
        $this->tools[$tool->id] = $tool;
    }

    public function all(): array
    {
        return \array_values($this->tools);
    }

    public function hasTools(): bool
    {
        return !empty($this->tools);
    }

    public function get(string $id): RagToolConfig
    {
        if (!$this->has($id)) {
            throw new \InvalidArgumentException(\sprintf('RAG tool "%s" not found', $id));
        }

        return $this->tools[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->tools[$id]);
    }
}
