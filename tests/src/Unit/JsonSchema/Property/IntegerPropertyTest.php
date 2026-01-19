<?php

declare(strict_types=1);

namespace Tests\Unit\JsonSchema\Property;

use Butschster\ContextGenerator\JsonSchema\Property\IntegerProperty;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IntegerPropertyTest extends TestCase
{
    #[Test]
    public function it_should_create_basic_integer_type(): void
    {
        $property = IntegerProperty::new();

        $this->assertEquals(['type' => 'integer'], $property->toArray());
    }

    #[Test]
    public function it_should_add_minimum_constraint(): void
    {
        $property = IntegerProperty::new()->minimum(0);

        $this->assertEquals([
            'type' => 'integer',
            'minimum' => 0,
        ], $property->toArray());
    }

    #[Test]
    public function it_should_add_maximum_constraint(): void
    {
        $property = IntegerProperty::new()->maximum(100);

        $this->assertEquals([
            'type' => 'integer',
            'maximum' => 100,
        ], $property->toArray());
    }

    #[Test]
    public function it_should_add_range_constraints(): void
    {
        $property = IntegerProperty::new()->minimum(1)->maximum(10);

        $this->assertEquals([
            'type' => 'integer',
            'minimum' => 1,
            'maximum' => 10,
        ], $property->toArray());
    }

    #[Test]
    public function it_should_add_description_and_default(): void
    {
        $property = IntegerProperty::new()
            ->description('Count')
            ->default(5);

        $this->assertEquals([
            'type' => 'integer',
            'description' => 'Count',
            'default' => 5,
        ], $property->toArray());
    }

    #[Test]
    public function it_should_return_empty_references(): void
    {
        $property = IntegerProperty::new();

        $this->assertEquals([], $property->getReferences());
    }
}
