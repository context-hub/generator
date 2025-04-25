<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt;

use Butschster\ContextGenerator\McpServer\Prompt\Extension\PromptDefinition;

interface PromptProviderInterface
{
    /**
     * Checks if a prompt with the given name exists.
     *
     * @param string $name The name of the prompt
     */
    public function has(string $name): bool;

    /**
     * Gets a prompt by name.
     *
     * @param string $name The name of the prompt
     * @throws \InvalidArgumentException If no prompt with the given name exists
     */
    public function get(string $name, array $arguments = []): PromptDefinition;

    /**
     * Gets all prompts.
     *
     * @return array<string, PromptDefinition>
     */
    public function all(): array;

    /**
     * Gets all non-template prompts.
     *
     * @return array<string, PromptDefinition>
     */
    public function allTemplates(): array;

    /**
     * Gets all prompts.
     *
     * @return list<PromptDefinition>
     */
    public function allPrompts(): array;
}
