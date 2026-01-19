<?php

declare(strict_types=1);

namespace Tests\Unit\JsonSchema;

use Butschster\ContextGenerator\JsonSchema\Definition\EnumDefinition;
use Butschster\ContextGenerator\JsonSchema\Definition\ObjectDefinition;
use Butschster\ContextGenerator\JsonSchema\Exception\DuplicateDefinitionException;
use Butschster\ContextGenerator\JsonSchema\Exception\SchemaValidationException;
use Butschster\ContextGenerator\JsonSchema\Property\ArrayProperty;
use Butschster\ContextGenerator\JsonSchema\Property\RefProperty;
use Butschster\ContextGenerator\JsonSchema\Property\StringProperty;
use Butschster\ContextGenerator\JsonSchema\SchemaDefinitionRegistry;
use Butschster\ContextGenerator\JsonSchema\SchemaMetadata;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SchemaDefinitionRegistryTest extends TestCase
{
    private SchemaDefinitionRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new SchemaDefinitionRegistry();
    }

    #[Test]
    public function it_should_define_and_return_object_definition(): void
    {
        $definition = $this->registry->define('testDef');

        $this->assertInstanceOf(ObjectDefinition::class, $definition);
        $this->assertEquals('testDef', $definition->getName());
    }

    #[Test]
    public function it_should_register_definition(): void
    {
        $this->registry->define('test');

        $this->assertTrue($this->registry->has('test'));
        $this->assertNotNull($this->registry->get('test'));
    }

    #[Test]
    public function it_should_throw_on_duplicate_define(): void
    {
        $this->registry->define('dup');

        $this->expectException(DuplicateDefinitionException::class);
        $this->registry->define('dup');
    }

    #[Test]
    public function it_should_register_pre_built_definition(): void
    {
        $enum = EnumDefinition::string('status', ['a', 'b']);
        $this->registry->register($enum);

        $this->assertTrue($this->registry->has('status'));
        $this->assertSame($enum, $this->registry->get('status'));
    }

    #[Test]
    public function it_should_throw_on_duplicate_register(): void
    {
        $this->registry->register(EnumDefinition::string('dup', ['x']));

        $this->expectException(DuplicateDefinitionException::class);
        $this->registry->register(EnumDefinition::string('dup', ['y']));
    }

    #[Test]
    public function it_should_return_null_for_missing_definition(): void
    {
        $this->assertNull($this->registry->get('nonexistent'));
        $this->assertFalse($this->registry->has('nonexistent'));
    }

    #[Test]
    public function it_should_count_definitions(): void
    {
        $this->assertEquals(0, $this->registry->count());

        $this->registry->define('one');
        $this->registry->define('two');

        $this->assertEquals(2, $this->registry->count());
    }

    #[Test]
    public function it_should_return_definition_names(): void
    {
        $this->registry->define('alpha');
        $this->registry->define('beta');

        $names = $this->registry->getDefinitionNames();

        $this->assertContains('alpha', $names);
        $this->assertContains('beta', $names);
    }

    #[Test]
    public function it_should_build_schema_with_metadata(): void
    {
        $this->registry->setMetadata(new SchemaMetadata(
            title: 'Test Schema',
            description: 'A test',
        ));

        $schema = $this->registry->build();

        $this->assertEquals('Test Schema', $schema['title']);
        $this->assertEquals('A test', $schema['description']);
        $this->assertEquals('object', $schema['type']);
    }

    #[Test]
    public function it_should_build_schema_with_root_properties(): void
    {
        $this->registry->addRootProperty('name', StringProperty::new());

        $schema = $this->registry->build();

        $this->assertArrayHasKey('properties', $schema);
        $this->assertEquals(['type' => 'string'], $schema['properties']['name']);
    }

    #[Test]
    public function it_should_build_schema_with_root_required(): void
    {
        $this->registry
            ->addRootProperty('name', StringProperty::new())
            ->addRootRequired('name');

        $schema = $this->registry->build();

        $this->assertEquals(['name'], $schema['required']);
    }

    #[Test]
    public function it_should_build_schema_with_root_one_of(): void
    {
        $this->registry
            ->addRootProperty('docs', StringProperty::new())
            ->addRootOneOf(['required' => ['docs']]);

        $schema = $this->registry->build();

        $this->assertArrayHasKey('oneOf', $schema);
        $this->assertEquals([['required' => ['docs']]], $schema['oneOf']);
        // required should not be in schema when oneOf is present
        $this->assertArrayNotHasKey('required', $schema);
    }

    #[Test]
    public function it_should_build_schema_with_sorted_definitions(): void
    {
        $this->registry->define('zebra');
        $this->registry->define('alpha');
        $this->registry->define('middle');

        $schema = $this->registry->build();

        $keys = array_keys($schema['definitions']);
        $this->assertEquals(['alpha', 'middle', 'zebra'], $keys);
    }

    #[Test]
    public function it_should_validate_references_successfully(): void
    {
        $this->registry->define('target');
        $this->registry->define('source')
            ->property('ref', RefProperty::to('target'));

        // Should not throw
        $schema = $this->registry->build();

        $this->assertArrayHasKey('definitions', $schema);
    }

    #[Test]
    public function it_should_throw_on_missing_reference_in_definition(): void
    {
        $this->registry->define('source')
            ->property('ref', RefProperty::to('missing'));

        $this->expectException(SchemaValidationException::class);
        $this->registry->build();
    }

    #[Test]
    public function it_should_throw_on_missing_reference_in_root_property(): void
    {
        $this->registry->addRootProperty('items', ArrayProperty::of(RefProperty::to('missing')));

        $this->expectException(SchemaValidationException::class);
        $this->registry->build();
    }

    #[Test]
    public function it_should_include_missing_references_in_exception(): void
    {
        $this->registry->define('source')
            ->property('a', RefProperty::to('missingA'))
            ->property('b', RefProperty::to('missingB'));

        try {
            $this->registry->build();
            $this->fail('Expected SchemaValidationException');
        } catch (SchemaValidationException $e) {
            $this->assertContains('missingA', $e->missingReferences);
            $this->assertContains('missingB', $e->missingReferences);
            $this->assertNotEmpty($e->errors);
        }
    }

    #[Test]
    public function it_should_include_usage_location_in_error(): void
    {
        $this->registry->define('source')
            ->property('ref', RefProperty::to('missing'));

        try {
            $this->registry->build();
            $this->fail('Expected SchemaValidationException');
        } catch (SchemaValidationException $e) {
            $this->assertStringContainsString('definitions.source', $e->errors[0]);
        }
    }

    #[Test]
    public function it_should_build_complete_schema(): void
    {
        $this->registry->setMetadata(new SchemaMetadata(
            title: 'Complete Test',
        ));

        $this->registry->define('itemType')
            ->property('name', StringProperty::new());

        $this->registry
            ->addRootProperty('items', ArrayProperty::of(RefProperty::to('itemType')))
            ->addRootRequired('items');

        $schema = $this->registry->build();

        $this->assertEquals('Complete Test', $schema['title']);
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('definitions', $schema);
        $this->assertEquals(['items'], $schema['required']);
    }
}
