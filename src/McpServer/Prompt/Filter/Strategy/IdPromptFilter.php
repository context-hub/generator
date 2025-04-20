<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt\Filter\Strategy;

use Butschster\ContextGenerator\McpServer\Prompt\Filter\PromptFilterInterface;

/**
 * Filters prompts by their IDs.
 */
final readonly class IdPromptFilter implements PromptFilterInterface
{
    /**
     * @param string[] $ids List of prompt IDs to include
     */
    public function __construct(
        private array $ids,
    ) {}

    public function shouldInclude(array $promptConfig): bool
    {
        // If no IDs specified, include all prompts
        if (empty($this->ids)) {
            return true;
        }

        // If prompt has no ID, exclude it
        if (!isset($promptConfig['id']) || !\is_string($promptConfig['id'])) {
            return false;
        }

        // Include if the prompt ID is in the list
        return \in_array($promptConfig['id'], $this->ids, true);
    }
}
