<?php

declare(strict_types=1);

namespace Tests\Unit\JsonSchema\Property;

use Butschster\ContextGenerator\JsonSchema\Property\ObjectProperty;
use Butschster\ContextGenerator\JsonSchema\Property\RefProperty;
use Butschster\ContextGenerator\JsonSchema\Property\StringProperty;
use Butschster\ContextGenerator\JsonSchema\Property\IntegerProperty;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ObjectPropertyTest extends TestCase
{
    #[Test]
    public function it_should_create_basic_object_type(): void
    {
        $property = ObjectProperty::new();

        $this->assertEquals(['type' => 'object'], $property->toArray());
    }

    #[Test]
    public function it_should_add_properties(): void
    {
        $property = ObjectProperty::new()
            ->property('name', StringProperty::new())
            ->property('age', IntegerProperty::new());

        $this->assertEquals([
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer'],
            ],
        ], $property->toArray());
    }

    #[Test]
    public function it_should_add_required_fields(): void
    {
        $property = ObjectProperty::new()
            ->property('name', StringProperty::new())
            ->required('name');

        $this->assertEquals([
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
            ],
            'required' => ['name'],
        ], $property->toArray());
    }

    #[Test]
    public function it_should_add_multiple_required_fields(): void
    {
        $property = ObjectProperty::new()
            ->property('name', StringProperty::new())
            ->property('age', IntegerProperty::new())
            ->required('name', 'age');

        $result = $property->toArray();

        $this->assertEquals(['name', 'age'], $result['required']);
    }

    #[Test]
    public function it_should_deduplicate_required_fields(): void
    {
        $property = ObjectProperty::new()
            ->property('name', StringProperty::new())
            ->required('name')
            ->required('name');

        $result = $property->toArray();

        $this->assertEquals(['name'], $result['required']);
    }

    #[Test]
    public function it_should_disable_additional_properties(): void
    {
        $property = ObjectProperty::new()
            ->additionalProperties(false);

        $this->assertEquals([
            'type' => 'object',
            'additionalProperties' => false,
        ], $property->toArray());
    }

    #[Test]
    public function it_should_set_additional_properties_schema(): void
    {
        $property = ObjectProperty::new()
            ->additionalProperties(StringProperty::new());

        $this->assertEquals([
            'type' => 'object',
            'additionalProperties' => ['type' => 'string'],
        ], $property->toArray());
    }

    #[Test]
    public function it_should_return_empty_references_for_simple_properties(): void
    {
        $property = ObjectProperty::new()
            ->property('name', StringProperty::new());

        $this->assertEquals([], $property->getReferences());
    }

    #[Test]
    public function it_should_collect_references_from_properties(): void
    {
        $property = ObjectProperty::new()
            ->property('config', RefProperty::to('configDef'))
            ->property('items', RefProperty::to('itemsDef'));

        $this->assertEquals(['configDef', 'itemsDef'], $property->getReferences());
    }

    #[Test]
    public function it_should_collect_references_from_additional_properties(): void
    {
        $property = ObjectProperty::new()
            ->additionalProperties(RefProperty::to('valueDef'));

        $this->assertEquals(['valueDef'], $property->getReferences());
    }
}
