<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt;

interface PromptRegistryInterface
{
    /**
     * Registers a prompt in the registry.
     *
     * @throws \InvalidArgumentException If a prompt with the same name already exists
     */
    public function register(PromptDefinition $prompt): void;
}
