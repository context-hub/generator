<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt\Filter;

interface PromptFilterInterface
{
    /**
     * Checks if the prompt configuration should be included based on filter criteria.
     */
    public function shouldInclude(array $promptConfig): bool;
}
