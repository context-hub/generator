<?php

declare(strict_types=1);

namespace Tests\Feature\McpServer\Prompt\Filter;

use Butschster\ContextGenerator\McpServer\Prompt\Filter\FilterStrategy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for FilterStrategy enum.
 */
final class FilterStrategyTest extends TestCase
{
    #[Test]
    public function fromStringShouldHandleValidValues(): void
    {
        // Test case-insensitive matching
        $this->assertEquals(FilterStrategy::ALL, FilterStrategy::fromString('all'));
        $this->assertEquals(FilterStrategy::ALL, FilterStrategy::fromString('ALL'));
        $this->assertEquals(FilterStrategy::ALL, FilterStrategy::fromString('All'));

        // ANY should be the default for any other string
        $this->assertEquals(FilterStrategy::ANY, FilterStrategy::fromString('any'));
        $this->assertEquals(FilterStrategy::ANY, FilterStrategy::fromString('ANY'));
    }

    #[Test]
    public function fromStringShouldHandleInvalidValues(): void
    {
        // Default to ANY for invalid values
        $this->assertEquals(FilterStrategy::ANY, FilterStrategy::fromString('invalid'));
        $this->assertEquals(FilterStrategy::ANY, FilterStrategy::fromString(''));
        $this->assertEquals(FilterStrategy::ANY, FilterStrategy::fromString(null));
    }

    #[Test]
    public function enumValuesShouldMatchExpected(): void
    {
        // String values should match what's expected in configs
        $this->assertEquals('any', FilterStrategy::ANY->value);
        $this->assertEquals('all', FilterStrategy::ALL->value);
    }
}
