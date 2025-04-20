<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt\Filter;

use Butschster\ContextGenerator\McpServer\Prompt\Filter\Strategy\CompositePromptFilter;
use Butschster\ContextGenerator\McpServer\Prompt\Filter\Strategy\IdPromptFilter;
use Butschster\ContextGenerator\McpServer\Prompt\Filter\Strategy\TagPromptFilter;

final readonly class PromptFilterFactory
{
    /**
     * Creates a prompt filter from a filter configuration.
     *
     * @param array<string, mixed>|null $filterConfig The filter configuration
     * @return PromptFilterInterface|null The created filter, or null if no filter needed
     */
    public function createFromConfig(?array $filterConfig): ?PromptFilterInterface
    {
        // If no filter config, return null (no filtering)
        if (empty($filterConfig)) {
            return null;
        }

        $filters = [];

        // Create ID filter if IDs specified
        if (isset($filterConfig['ids']) && \is_array($filterConfig['ids'])) {
            $ids = $this->extractStringValues($filterConfig['ids']);
            if (!empty($ids)) {
                $filters[] = new IdPromptFilter($ids);
            }
        }

        // Create tag filter if tags specified
        if (isset($filterConfig['tags']) && \is_array($filterConfig['tags'])) {
            $includeTags = [];
            $excludeTags = [];

            // Extract include tags
            if (isset($filterConfig['tags']['include']) && \is_array($filterConfig['tags']['include'])) {
                $includeTags = $this->extractStringValues($filterConfig['tags']['include']);
            }

            // Extract exclude tags
            if (isset($filterConfig['tags']['exclude']) && \is_array($filterConfig['tags']['exclude'])) {
                $excludeTags = $this->extractStringValues($filterConfig['tags']['exclude']);
            }

            // Create tag filter if any tags specified
            if (!empty($includeTags) || !empty($excludeTags)) {
                $strategy = FilterStrategy::fromString($filterConfig['tags']['match'] ?? null);
                $filters[] = new TagPromptFilter($includeTags, $excludeTags, $strategy);
            }
        }

        // If no filters created, return null
        if (empty($filters)) {
            return null;
        }

        // If only one filter, return it directly
        if (\count($filters) === 1) {
            return $filters[0];
        }

        // Otherwise, create a composite filter
        $strategy = FilterStrategy::fromString($filterConfig['match'] ?? null);
        return new CompositePromptFilter($filters, $strategy);
    }

    /**
     * Extracts string values from an array, skipping non-string values.
     *
     * @param array<mixed> $values The values to extract strings from
     * @return array<string> The extracted string values
     */
    private function extractStringValues(array $values): array
    {
        $result = [];
        foreach ($values as $value) {
            if (\is_string($value)) {
                $result[] = $value;
            }
        }
        return $result;
    }
}
