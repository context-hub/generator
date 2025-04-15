<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt\Extension;

/**
 * Represents an argument being passed to a template when extending it.
 */
final readonly class PromptExtensionArgument
{
    public function __construct(
        public string $name,
        public string $value,
    ) {}
}
