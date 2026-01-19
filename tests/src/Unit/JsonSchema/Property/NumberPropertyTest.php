<?php

declare(strict_types=1);

namespace Tests\Unit\JsonSchema\Property;

use Butschster\ContextGenerator\JsonSchema\Property\NumberProperty;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NumberPropertyTest extends TestCase
{
    #[Test]
    public function it_should_create_basic_number_type(): void
    {
        $property = NumberProperty::new();

        $this->assertEquals(['type' => 'number'], $property->toArray());
    }

    #[Test]
    public function it_should_add_minimum_constraint(): void
    {
        $property = NumberProperty::new()->minimum(0.5);

        $this->assertEquals([
            'type' => 'number',
            'minimum' => 0.5,
        ], $property->toArray());
    }

    #[Test]
    public function it_should_add_maximum_constraint(): void
    {
        $property = NumberProperty::new()->maximum(99.9);

        $this->assertEquals([
            'type' => 'number',
            'maximum' => 99.9,
        ], $property->toArray());
    }

    #[Test]
    public function it_should_add_range_constraints(): void
    {
        $property = NumberProperty::new()->minimum(0.0)->maximum(1.0);

        $this->assertEquals([
            'type' => 'number',
            'minimum' => 0.0,
            'maximum' => 1.0,
        ], $property->toArray());
    }

    #[Test]
    public function it_should_return_empty_references(): void
    {
        $property = NumberProperty::new();

        $this->assertEquals([], $property->getReferences());
    }
}
