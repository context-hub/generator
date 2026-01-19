<?php

declare(strict_types=1);

namespace Tests\Unit\JsonSchema\Definition;

use Butschster\ContextGenerator\JsonSchema\Definition\OneOfDefinition;
use Butschster\ContextGenerator\JsonSchema\Property\ArrayProperty;
use Butschster\ContextGenerator\JsonSchema\Property\RefProperty;
use Butschster\ContextGenerator\JsonSchema\Property\StringProperty;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OneOfDefinitionTest extends TestCase
{
    #[Test]
    public function it_should_create_one_of_definition(): void
    {
        $definition = OneOfDefinition::create(
            'sourcePaths',
            StringProperty::new()->description('Single path'),
            ArrayProperty::strings()->description('Multiple paths'),
        );

        $this->assertEquals('sourcePaths', $definition->getName());
    }

    #[Test]
    public function it_should_produce_one_of_array(): void
    {
        $definition = OneOfDefinition::create(
            'test',
            StringProperty::new(),
            ArrayProperty::strings(),
        );

        $this->assertEquals([
            'oneOf' => [
                ['type' => 'string'],
                ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ], $definition->toArray());
    }

    #[Test]
    public function it_should_return_empty_references_for_simple_types(): void
    {
        $definition = OneOfDefinition::create(
            'test',
            StringProperty::new(),
        );

        $this->assertEquals([], $definition->getReferences());
    }

    #[Test]
    public function it_should_collect_references_from_options(): void
    {
        $definition = OneOfDefinition::create(
            'test',
            RefProperty::to('typeA'),
            RefProperty::to('typeB'),
        );

        $this->assertEquals(['typeA', 'typeB'], $definition->getReferences());
    }

    #[Test]
    public function it_should_deduplicate_references(): void
    {
        $definition = OneOfDefinition::create(
            'test',
            RefProperty::to('same'),
            RefProperty::to('same'),
        );

        $this->assertEquals(['same'], $definition->getReferences());
    }
}
