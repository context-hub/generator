<?php

declare(strict_types=1);

namespace Tests\Feature\McpServer\Prompt\Filter;

use Butschster\ContextGenerator\McpServer\Prompt\Filter\FilterStrategy;
use Butschster\ContextGenerator\McpServer\Prompt\Filter\Strategy\IdPromptFilter;
use Butschster\ContextGenerator\McpServer\Prompt\Filter\Strategy\TagPromptFilter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for individual prompt filters (ID and tag based).
 */
final class PromptFilterTest extends TestCase
{
    #[Test]
    public function idFilterShouldIncludeMatchingPrompts(): void
    {
        $filter = new IdPromptFilter(['prompt1', 'prompt2']);

        // Matching prompt
        $this->assertTrue($filter->shouldInclude(['id' => 'prompt1']));

        // Non-matching prompt
        $this->assertFalse($filter->shouldInclude(['id' => 'prompt3']));
    }

    #[Test]
    public function idFilterShouldHandleEdgeCases(): void
    {
        // Empty ID list should include all prompts
        $emptyFilter = new IdPromptFilter([]);
        $this->assertTrue($emptyFilter->shouldInclude(['id' => 'anything']));

        // Filter should exclude prompts without IDs
        $filter = new IdPromptFilter(['prompt1']);
        $this->assertFalse($filter->shouldInclude([]));
        $this->assertFalse($filter->shouldInclude(['name' => 'something']));
        $this->assertFalse($filter->shouldInclude(['id' => 123])); // Non-string ID
    }

    #[Test]
    public function tagFilterShouldIncludeWithAnyStrategy(): void
    {
        // Test "any" match strategy (default)
        $filter = new TagPromptFilter(
            includeTags: ['tag1', 'tag2'],
            strategy: FilterStrategy::ANY,
        );

        // Should include if any tag matches
        $this->assertTrue($filter->shouldInclude(['tags' => ['tag1', 'tag3']]));
        $this->assertTrue($filter->shouldInclude(['tags' => ['tag2']]));

        // Should exclude if no tags match
        $this->assertFalse($filter->shouldInclude(['tags' => ['tag3', 'tag4']]));
    }

    #[Test]
    public function tagFilterShouldIncludeWithAllStrategy(): void
    {
        // Test "all" match strategy
        $filter = new TagPromptFilter(
            includeTags: ['tag1', 'tag2'],
            strategy: FilterStrategy::ALL,
        );

        // Should include only if all specified tags are present
        $this->assertTrue($filter->shouldInclude(['tags' => ['tag1', 'tag2']]));
        $this->assertTrue($filter->shouldInclude(['tags' => ['tag1', 'tag2', 'tag3']]));

        // Should exclude if any specified tag is missing
        $this->assertFalse($filter->shouldInclude(['tags' => ['tag1']]));
        $this->assertFalse($filter->shouldInclude(['tags' => ['tag2']]));
    }

    #[Test]
    public function tagFilterShouldHandleExcludeTags(): void
    {
        $filter = new TagPromptFilter(
            includeTags: ['tag1'],
            excludeTags: ['tag2', 'tag3'],
        );

        // Should include if it has include tag and no exclude tags
        $this->assertTrue($filter->shouldInclude(['tags' => ['tag1', 'tag4']]));

        // Should exclude if it has any exclude tag, even if include tag is present
        $this->assertFalse($filter->shouldInclude(['tags' => ['tag1', 'tag2']]));
        $this->assertFalse($filter->shouldInclude(['tags' => ['tag1', 'tag3']]));
    }

    #[Test]
    public function tagFilterShouldHandleOnlyExcludeTags(): void
    {
        $filter = new TagPromptFilter(
            includeTags: [],
            excludeTags: ['tag1', 'tag2'],
        );

        // Should include if no exclude tags are present
        $this->assertTrue($filter->shouldInclude(['tags' => ['tag3', 'tag4']]));
        $this->assertTrue($filter->shouldInclude(['tags' => []]));

        // Should exclude if any exclude tag is present
        $this->assertFalse($filter->shouldInclude(['tags' => ['tag1']]));
        $this->assertFalse($filter->shouldInclude(['tags' => ['tag2']]));
        $this->assertFalse($filter->shouldInclude(['tags' => ['tag1', 'tag3']]));
    }

    #[Test]
    public function tagFilterShouldHandleEdgeCases(): void
    {
        // No include or exclude tags should include all prompts
        $emptyFilter = new TagPromptFilter();
        $this->assertTrue($emptyFilter->shouldInclude(['tags' => ['anything']]));
        $this->assertTrue($emptyFilter->shouldInclude(['tags' => []]));
        $this->assertTrue($emptyFilter->shouldInclude([]));

        // Include tags with no prompt tags should exclude
        $includeFilter = new TagPromptFilter(includeTags: ['tag1']);
        $this->assertFalse($includeFilter->shouldInclude(['tags' => []]));
        $this->assertFalse($includeFilter->shouldInclude([]));

        // Filter should handle non-array tags and non-string tag values
        $filter = new TagPromptFilter(includeTags: ['tag1']);
        $this->assertFalse($filter->shouldInclude(['tags' => 'not-an-array']));
        $this->assertFalse($filter->shouldInclude(['tags' => [123, true]]));
    }
}
