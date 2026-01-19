<?php

declare(strict_types=1);

namespace Tests\Unit\JsonSchema\Definition;

use Butschster\ContextGenerator\JsonSchema\Definition\ObjectDefinition;
use Butschster\ContextGenerator\JsonSchema\Property\RefProperty;
use Butschster\ContextGenerator\JsonSchema\Property\StringProperty;
use Butschster\ContextGenerator\JsonSchema\Property\IntegerProperty;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ObjectDefinitionTest extends TestCase
{
    #[Test]
    public function it_should_create_definition_with_name(): void
    {
        $definition = ObjectDefinition::create('testDef');

        $this->assertEquals('testDef', $definition->getName());
    }

    #[Test]
    public function it_should_create_empty_definition(): void
    {
        $definition = ObjectDefinition::create('empty');

        $this->assertEquals([], $definition->toArray());
    }

    #[Test]
    public function it_should_add_title(): void
    {
        $definition = ObjectDefinition::create('test')
            ->title('Test Definition');

        $this->assertEquals(['title' => 'Test Definition'], $definition->toArray());
    }

    #[Test]
    public function it_should_add_description(): void
    {
        $definition = ObjectDefinition::create('test')
            ->description('A test definition');

        $this->assertEquals(['description' => 'A test definition'], $definition->toArray());
    }

    #[Test]
    public function it_should_add_properties(): void
    {
        $definition = ObjectDefinition::create('person')
            ->property('name', StringProperty::new())
            ->property('age', IntegerProperty::new());

        $result = $definition->toArray();

        $this->assertEquals('object', $result['type']);
        $this->assertEquals(['type' => 'string'], $result['properties']['name']);
        $this->assertEquals(['type' => 'integer'], $result['properties']['age']);
    }

    #[Test]
    public function it_should_add_required_fields(): void
    {
        $definition = ObjectDefinition::create('person')
            ->property('name', StringProperty::new())
            ->required('name');

        $result = $definition->toArray();

        $this->assertEquals(['name'], $result['required']);
    }

    #[Test]
    public function it_should_deduplicate_required_fields(): void
    {
        $definition = ObjectDefinition::create('person')
            ->property('name', StringProperty::new())
            ->required('name')
            ->required('name');

        $result = $definition->toArray();

        $this->assertEquals(['name'], $result['required']);
    }

    #[Test]
    public function it_should_disable_additional_properties(): void
    {
        $definition = ObjectDefinition::create('strict')
            ->property('name', StringProperty::new())
            ->additionalProperties(false);

        $result = $definition->toArray();

        $this->assertFalse($result['additionalProperties']);
    }

    #[Test]
    public function it_should_set_additional_properties_schema(): void
    {
        $definition = ObjectDefinition::create('flexible')
            ->additionalProperties(StringProperty::new());

        $result = $definition->toArray();

        $this->assertEquals(['type' => 'string'], $result['additionalProperties']);
    }

    #[Test]
    public function it_should_return_empty_references_for_simple_properties(): void
    {
        $definition = ObjectDefinition::create('simple')
            ->property('name', StringProperty::new());

        $this->assertEquals([], $definition->getReferences());
    }

    #[Test]
    public function it_should_collect_references_from_properties(): void
    {
        $definition = ObjectDefinition::create('complex')
            ->property('config', RefProperty::to('configDef'))
            ->property('items', RefProperty::to('itemsDef'));

        $this->assertEquals(['configDef', 'itemsDef'], $definition->getReferences());
    }

    #[Test]
    public function it_should_collect_references_from_additional_properties(): void
    {
        $definition = ObjectDefinition::create('map')
            ->additionalProperties(RefProperty::to('valueDef'));

        $this->assertEquals(['valueDef'], $definition->getReferences());
    }

    #[Test]
    public function it_should_deduplicate_references(): void
    {
        $definition = ObjectDefinition::create('dup')
            ->property('first', RefProperty::to('sameDef'))
            ->property('second', RefProperty::to('sameDef'));

        $this->assertEquals(['sameDef'], $definition->getReferences());
    }
}
