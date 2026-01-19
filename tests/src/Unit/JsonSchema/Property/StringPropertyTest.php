<?php

declare(strict_types=1);

namespace Tests\Unit\JsonSchema\Property;

use Butschster\ContextGenerator\JsonSchema\Property\StringProperty;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StringPropertyTest extends TestCase
{
    #[Test]
    public function it_should_create_basic_string_type(): void
    {
        $property = StringProperty::new();

        $this->assertEquals(['type' => 'string'], $property->toArray());
    }

    #[Test]
    public function it_should_add_description(): void
    {
        $property = StringProperty::new()->description('A test string');

        $this->assertEquals([
            'type' => 'string',
            'description' => 'A test string',
        ], $property->toArray());
    }

    #[Test]
    public function it_should_add_default_value(): void
    {
        $property = StringProperty::new()->default('hello');

        $this->assertEquals([
            'type' => 'string',
            'default' => 'hello',
        ], $property->toArray());
    }

    #[Test]
    public function it_should_add_enum_constraint(): void
    {
        $property = StringProperty::new()->enum(['a', 'b', 'c']);

        $this->assertEquals([
            'type' => 'string',
            'enum' => ['a', 'b', 'c'],
        ], $property->toArray());
    }

    #[Test]
    public function it_should_add_pattern_constraint(): void
    {
        $property = StringProperty::new()->pattern('^[a-z]+$');

        $this->assertEquals([
            'type' => 'string',
            'pattern' => '^[a-z]+$',
        ], $property->toArray());
    }

    #[Test]
    public function it_should_add_format_constraint(): void
    {
        $property = StringProperty::new()->format('uri');

        $this->assertEquals([
            'type' => 'string',
            'format' => 'uri',
        ], $property->toArray());
    }

    #[Test]
    public function it_should_add_length_constraints(): void
    {
        $property = StringProperty::new()->minLength(1)->maxLength(100);

        $this->assertEquals([
            'type' => 'string',
            'minLength' => 1,
            'maxLength' => 100,
        ], $property->toArray());
    }

    #[Test]
    public function it_should_combine_all_constraints(): void
    {
        $property = StringProperty::new()
            ->enum(['foo', 'bar'])
            ->pattern('^f')
            ->format('custom')
            ->minLength(2)
            ->maxLength(5)
            ->description('Combined')
            ->default('foo');

        $result = $property->toArray();

        $this->assertEquals('string', $result['type']);
        $this->assertEquals(['foo', 'bar'], $result['enum']);
        $this->assertEquals('^f', $result['pattern']);
        $this->assertEquals('custom', $result['format']);
        $this->assertEquals(2, $result['minLength']);
        $this->assertEquals(5, $result['maxLength']);
        $this->assertEquals('Combined', $result['description']);
        $this->assertEquals('foo', $result['default']);
    }

    #[Test]
    public function it_should_return_empty_references(): void
    {
        $property = StringProperty::new();

        $this->assertEquals([], $property->getReferences());
    }
}
