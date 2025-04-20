<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt\Filter\Strategy;

use Butschster\ContextGenerator\McpServer\Prompt\Filter\FilterStrategy;
use Butschster\ContextGenerator\McpServer\Prompt\Filter\PromptFilterInterface;

/**
 * Filters prompts by their tags with include/exclude capabilities.
 */
final readonly class TagPromptFilter implements PromptFilterInterface
{
    /**
     * @param string[] $includeTags Tags to include
     * @param string[] $excludeTags Tags to exclude
     * @param FilterStrategy $strategy Matching strategy for include tags
     */
    public function __construct(
        private array $includeTags = [],
        private array $excludeTags = [],
        private FilterStrategy $strategy = FilterStrategy::ANY,
    ) {}

    public function shouldInclude(array $promptConfig): bool
    {
        // If no tags specified for filtering, include all prompts
        if (empty($this->includeTags) && empty($this->excludeTags)) {
            return true;
        }

        // Get prompt tags
        $promptTags = $this->getPromptTags($promptConfig);

        // If excluded tags match, exclude the prompt
        if (!empty($this->excludeTags) && !empty($promptTags)) {
            foreach ($this->excludeTags as $excludeTag) {
                if (\in_array($excludeTag, $promptTags, true)) {
                    return false;
                }
            }
        }

        // If no include tags specified, include the prompt
        if (empty($this->includeTags)) {
            return true;
        }

        // If prompt has no tags but include tags are specified, exclude it
        if (empty($promptTags)) {
            return false;
        }

        // Check if prompt tags match include tags based on strategy
        return match ($this->strategy) {
            FilterStrategy::ALL => $this->allTagsMatch($promptTags),
            FilterStrategy::ANY => $this->anyTagMatches($promptTags),
        };
    }

    /**
     * Checks if all include tags are present in the prompt tags.
     */
    private function allTagsMatch(array $promptTags): bool
    {
        foreach ($this->includeTags as $includeTag) {
            if (!\in_array($includeTag, $promptTags, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if any include tag is present in the prompt tags.
     */
    private function anyTagMatches(array $promptTags): bool
    {
        foreach ($this->includeTags as $includeTag) {
            if (\in_array($includeTag, $promptTags, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets the tags from a prompt configuration.
     */
    private function getPromptTags(array $promptConfig): array
    {
        if (!isset($promptConfig['tags']) || !\is_array($promptConfig['tags'])) {
            return [];
        }

        $tags = [];
        foreach ($promptConfig['tags'] as $tag) {
            if (\is_string($tag)) {
                $tags[] = $tag;
            }
        }

        return $tags;
    }
}
