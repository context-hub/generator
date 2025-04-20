<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt\Filter\Strategy;

use Butschster\ContextGenerator\McpServer\Prompt\Filter\FilterStrategy;
use Butschster\ContextGenerator\McpServer\Prompt\Filter\PromptFilterInterface;

/**
 * Combines multiple filters with a specified matching strategy.
 */
final readonly class CompositePromptFilter implements PromptFilterInterface
{
    public function __construct(
        private array $filters,
        private FilterStrategy $strategy = FilterStrategy::ALL,
    ) {}

    public function shouldInclude(array $promptConfig): bool
    {
        // If no filters, include all prompts
        if (empty($this->filters)) {
            return true;
        }

        return match ($this->strategy) {
            FilterStrategy::ALL => $this->allFiltersMatch($promptConfig),
            FilterStrategy::ANY => $this->anyFilterMatches($promptConfig),
        };
    }

    /**
     * Checks if all filters match (AND logic).
     */
    private function allFiltersMatch(array $promptConfig): bool
    {
        foreach ($this->filters as $filter) {
            if (!$filter->shouldInclude($promptConfig)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if any filter matches (OR logic).
     */
    private function anyFilterMatches(array $promptConfig): bool
    {
        foreach ($this->filters as $filter) {
            if ($filter->shouldInclude($promptConfig)) {
                return true;
            }
        }

        return false;
    }
}
