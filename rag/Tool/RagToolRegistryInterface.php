<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Tool;

/**
 * Registry for RAG tool configurations.
 */
interface RagToolRegistryInterface
{
    /**
     * Register a RAG tool configuration.
     */
    public function register(RagToolConfig $tool): void;

    /**
     * Get all registered RAG tool configurations.
     *
     * @return RagToolConfig[]
     */
    public function all(): array;

    /**
     * Check if any RAG tools are registered.
     */
    public function hasTools(): bool;

    /**
     * Get a tool by ID.
     *
     * @throws \InvalidArgumentException If tool not found
     */
    public function get(string $id): RagToolConfig;

    /**
     * Check if a tool exists.
     */
    public function has(string $id): bool;
}
