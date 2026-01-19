<?php

declare(strict_types=1);

namespace Tests\Unit\JsonSchema\Property;

use Butschster\ContextGenerator\JsonSchema\Property\ArrayProperty;
use Butschster\ContextGenerator\JsonSchema\Property\RefProperty;
use Butschster\ContextGenerator\JsonSchema\Property\StringProperty;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ArrayPropertyTest extends TestCase
{
    #[Test]
    public function it_should_create_basic_array_type(): void
    {
        $property = ArrayProperty::new();

        $this->assertEquals(['type' => 'array'], $property->toArray());
    }

    #[Test]
    public function it_should_create_array_of_strings_shortcut(): void
    {
        $property = ArrayProperty::strings();

        $this->assertEquals([
            'type' => 'array',
            'items' => ['type' => 'string'],
        ], $property->toArray());
    }

    #[Test]
    public function it_should_create_array_of_specific_type(): void
    {
        $property = ArrayProperty::of(StringProperty::new()->minLength(1));

        $this->assertEquals([
            'type' => 'array',
            'items' => [
                'type' => 'string',
                'minLength' => 1,
            ],
        ], $property->toArray());
    }

    #[Test]
    public function it_should_add_items_constraint(): void
    {
        $property = ArrayProperty::new()->items(StringProperty::new());

        $this->assertEquals([
            'type' => 'array',
            'items' => ['type' => 'string'],
        ], $property->toArray());
    }

    #[Test]
    public function it_should_add_min_items_constraint(): void
    {
        $property = ArrayProperty::new()->minItems(1);

        $this->assertEquals([
            'type' => 'array',
            'minItems' => 1,
        ], $property->toArray());
    }

    #[Test]
    public function it_should_add_max_items_constraint(): void
    {
        $property = ArrayProperty::new()->maxItems(10);

        $this->assertEquals([
            'type' => 'array',
            'maxItems' => 10,
        ], $property->toArray());
    }

    #[Test]
    public function it_should_combine_all_constraints(): void
    {
        $property = ArrayProperty::new()
            ->items(StringProperty::new())
            ->minItems(1)
            ->maxItems(5)
            ->description('List of strings');

        $this->assertEquals([
            'type' => 'array',
            'items' => ['type' => 'string'],
            'minItems' => 1,
            'maxItems' => 5,
            'description' => 'List of strings',
        ], $property->toArray());
    }

    #[Test]
    public function it_should_return_empty_references_for_simple_types(): void
    {
        $property = ArrayProperty::strings();

        $this->assertEquals([], $property->getReferences());
    }

    #[Test]
    public function it_should_collect_references_from_items(): void
    {
        $property = ArrayProperty::of(RefProperty::to('someDefinition'));

        $this->assertEquals(['someDefinition'], $property->getReferences());
    }

    #[Test]
    public function it_should_return_empty_references_when_no_items(): void
    {
        $property = ArrayProperty::new();

        $this->assertEquals([], $property->getReferences());
    }
}
