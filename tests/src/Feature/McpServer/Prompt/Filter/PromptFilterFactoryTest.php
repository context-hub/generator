<?php

declare(strict_types=1);

namespace Tests\Feature\McpServer\Prompt\Filter;

use Butschster\ContextGenerator\McpServer\Prompt\Filter\PromptFilterFactory;
use Butschster\ContextGenerator\McpServer\Prompt\Filter\Strategy\CompositePromptFilter;
use Butschster\ContextGenerator\McpServer\Prompt\Filter\Strategy\IdPromptFilter;
use Butschster\ContextGenerator\McpServer\Prompt\Filter\Strategy\TagPromptFilter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for PromptFilterFactory which creates filter objects from configuration.
 */
final class PromptFilterFactoryTest extends TestCase
{
    private PromptFilterFactory $factory;

    #[Test]
    public function factoryShouldCreateIdFilter(): void
    {
        $config = [
            'ids' => ['prompt1', 'prompt2'],
        ];

        $filter = $this->factory->createFromConfig($config);

        $this->assertInstanceOf(IdPromptFilter::class, $filter);
        $this->assertTrue($filter->shouldInclude(['id' => 'prompt1']));
        $this->assertFalse($filter->shouldInclude(['id' => 'prompt3']));
    }

    #[Test]
    public function factoryShouldCreateTagFilter(): void
    {
        $config = [
            'tags' => [
                'include' => ['tag1', 'tag2'],
                'match' => 'any',
            ],
        ];

        $filter = $this->factory->createFromConfig($config);

        $this->assertInstanceOf(TagPromptFilter::class, $filter);
        $this->assertTrue($filter->shouldInclude(['tags' => ['tag1']]));
        $this->assertFalse($filter->shouldInclude(['tags' => ['tag3']]));
    }

    #[Test]
    public function factoryShouldCreateTagFilterWithExcludeTags(): void
    {
        $config = [
            'tags' => [
                'include' => ['tag1'],
                'exclude' => ['tag2'],
                'match' => 'all',
            ],
        ];

        $filter = $this->factory->createFromConfig($config);

        $this->assertInstanceOf(TagPromptFilter::class, $filter);
        $this->assertTrue($filter->shouldInclude(['tags' => ['tag1', 'tag3']]));
        $this->assertFalse($filter->shouldInclude(['tags' => ['tag1', 'tag2']])); // Has excluded tag
    }

    #[Test]
    public function factoryShouldCreateCompositeFilter(): void
    {
        $config = [
            'ids' => ['prompt1', 'prompt2'],
            'tags' => [
                'include' => ['tag1'],
            ],
            'match' => 'all',
        ];

        $filter = $this->factory->createFromConfig($config);

        $this->assertInstanceOf(CompositePromptFilter::class, $filter);
        $this->assertTrue($filter->shouldInclude(['id' => 'prompt1', 'tags' => ['tag1']]));
        $this->assertFalse($filter->shouldInclude(['id' => 'prompt1', 'tags' => ['tag2']]));
    }

    #[Test]
    public function factoryShouldHandleInvalidValues(): void
    {
        // Non-string IDs should be filtered out
        $config = [
            'ids' => ['prompt1', 123, true, null],
        ];

        $filter = $this->factory->createFromConfig($config);
        $this->assertTrue($filter->shouldInclude(['id' => 'prompt1']));
        $this->assertFalse($filter->shouldInclude(['id' => '123'])); // Was filtered out

        // Non-string tag values should be filtered out
        $config = [
            'tags' => [
                'include' => ['tag1', 123, true, null],
                'exclude' => ['tag2', 456, false],
            ],
        ];

        $filter = $this->factory->createFromConfig($config);
        $this->assertTrue($filter->shouldInclude(['tags' => ['tag1']]));
        $this->assertFalse($filter->shouldInclude(['tags' => ['123']])); // Was filtered out
    }

    #[Test]
    public function factoryShouldHandleEmptyConfig(): void
    {
        // Empty config should return null (no filtering)
        $this->assertNull($this->factory->createFromConfig(null));
        $this->assertNull($this->factory->createFromConfig([]));

        // Config with empty filter sections should return null
        $this->assertNull($this->factory->createFromConfig(['ids' => []]));
        $this->assertNull($this->factory->createFromConfig(['tags' => []]));
        $this->assertNull($this->factory->createFromConfig(['tags' => ['include' => [], 'exclude' => []]]));
    }

    #[Test]
    public function factoryShouldRespectMatchStrategy(): void
    {
        // Test ANY strategy (default)
        $config = [
            'ids' => ['prompt1'],
            'tags' => [
                'include' => ['tag1'],
            ],
        ];

        $filter = $this->factory->createFromConfig($config);
        $this->assertTrue($filter->shouldInclude(['id' => 'prompt1', 'tags' => []]));
        $this->assertTrue($filter->shouldInclude(['id' => 'other', 'tags' => ['tag1']]));

        // Test ALL strategy
        $config = [
            'ids' => ['prompt1'],
            'tags' => [
                'include' => ['tag1'],
            ],
            'match' => 'all',
        ];

        $filter = $this->factory->createFromConfig($config);
        $this->assertFalse($filter->shouldInclude(['id' => 'prompt1', 'tags' => []]));
        $this->assertFalse($filter->shouldInclude(['id' => 'other', 'tags' => ['tag1']]));
        $this->assertTrue($filter->shouldInclude(['id' => 'prompt1', 'tags' => ['tag1']]));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new PromptFilterFactory();
    }
}
