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
}
