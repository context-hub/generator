<?php

declare(strict_types=1);

namespace Tests\Feature\McpServer\Prompt\Filter;

use Butschster\ContextGenerator\McpServer\Prompt\Filter\FilterStrategy;
use Butschster\ContextGenerator\McpServer\Prompt\Filter\Strategy\CompositePromptFilter;
use Butschster\ContextGenerator\McpServer\Prompt\Filter\Strategy\IdPromptFilter;
use Butschster\ContextGenerator\McpServer\Prompt\Filter\Strategy\TagPromptFilter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for composite prompt filters.
 */
final class CompositeFilterTest extends TestCase
{
    #[Test]
    public function compositeShouldApplyAndLogic(): void
    {
        $idFilter = new IdPromptFilter(['prompt1', 'prompt2']);
        $tagFilter = new TagPromptFilter(includeTags: ['tag1']);

        $composite = new CompositePromptFilter([$idFilter, $tagFilter], FilterStrategy::ALL);

        // Should include if both filters include
        $this->assertTrue($composite->shouldInclude([
            'id' => 'prompt1',
            'tags' => ['tag1'],
        ]));

        // Should exclude if any filter excludes
        $this->assertFalse($composite->shouldInclude([
            'id' => 'prompt1',
            'tags' => ['tag2'],  // Missing required tag
        ]));

        $this->assertFalse($composite->shouldInclude([
            'id' => 'prompt3',  // ID not in list
            'tags' => ['tag1'],
        ]));
    }

    #[Test]
    public function compositeShouldApplyOrLogic(): void
    {
        $idFilter = new IdPromptFilter(['prompt1', 'prompt2']);
        $tagFilter = new TagPromptFilter(includeTags: ['tag1']);

        $composite = new CompositePromptFilter([$idFilter, $tagFilter], FilterStrategy::ANY);

        // Should include if either filter includes
        $this->assertTrue($composite->shouldInclude([
            'id' => 'prompt1',
            'tags' => ['tag2'],  // Tag doesn't match but ID does
        ]));

        $this->assertTrue($composite->shouldInclude([
            'id' => 'prompt3',  // ID doesn't match
            'tags' => ['tag1'],  // But tag does
        ]));

        // Should exclude only if all filters exclude
        $this->assertFalse($composite->shouldInclude([
            'id' => 'prompt3',
            'tags' => ['tag2'],
        ]));
    }

    #[Test]
    public function compositeShouldHandleEmptyFilters(): void
    {
        // Empty filter list should include everything
        $emptyComposite = new CompositePromptFilter([]);
        $this->assertTrue($emptyComposite->shouldInclude(['id' => 'anything']));
    }

    #[Test]
    public function compositeShouldWorkWithNestedComposites(): void
    {
        // Create nested composite structure:
        // Top level: ANY strategy
        // - Child 1: ALL strategy with ID and tag filters
        // - Child 2: Simple ID filter

        $innerComposite = new CompositePromptFilter([
            new IdPromptFilter(['prompt1', 'prompt2']),
            new TagPromptFilter(includeTags: ['tag1']),
        ], FilterStrategy::ALL);

        $outerComposite = new CompositePromptFilter([
            $innerComposite,
            new IdPromptFilter(['prompt3']),
        ], FilterStrategy::ANY);

        // Should match inner composite (matches both inner filters)
        $this->assertTrue($outerComposite->shouldInclude([
            'id' => 'prompt1',
            'tags' => ['tag1'],
        ]));

        // Should match second filter in outer composite
        $this->assertTrue($outerComposite->shouldInclude([
            'id' => 'prompt3',
            'tags' => ['tag2'],
        ]));

        // Should not match either condition
        $this->assertFalse($outerComposite->shouldInclude([
            'id' => 'prompt1',  // First part of inner composite
            'tags' => ['tag2'],  // But fails second part of inner composite
        ]));

        $this->assertFalse($outerComposite->shouldInclude([
            'id' => 'prompt4',
            'tags' => ['tag2'],
        ]));
    }
}
