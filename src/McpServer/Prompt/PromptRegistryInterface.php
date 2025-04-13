<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt;

use Butschster\ContextGenerator\McpServer\Prompt\Extension\PromptDefinition;

interface PromptRegistryInterface
{
    /**
     * Registers a prompt in the registry.
     */
    public function register(PromptDefinition $prompt): void;

    /**
     * Checks if the registry has a prompt with the given ID.
     *
     * @param string $id The prompt ID
     */
    public function has(string $id): bool;

    /**
     * Gets a prompt by ID.
     *
     * @param string $id The prompt ID
     * @throws \InvalidArgumentException If no prompt with the given ID exists
     */
    public function get(string $id): PromptDefinition;
}
