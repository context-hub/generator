<?php

declare(strict_types=1);

namespace Tests\Unit\JsonSchema\Definition;

use Butschster\ContextGenerator\JsonSchema\Definition\EnumDefinition;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EnumDefinitionTest extends TestCase
{
    #[Test]
    public function it_should_create_string_enum(): void
    {
        $definition = EnumDefinition::string('status', ['active', 'inactive', 'pending']);

        $this->assertEquals('status', $definition->getName());
        $this->assertEquals([
            'type' => 'string',
            'enum' => ['active', 'inactive', 'pending'],
        ], $definition->toArray());
    }

    #[Test]
    public function it_should_create_integer_enum(): void
    {
        $definition = EnumDefinition::integer('priority', [1, 2, 3]);

        $this->assertEquals('priority', $definition->getName());
        $this->assertEquals([
            'type' => 'integer',
            'enum' => [1, 2, 3],
        ], $definition->toArray());
    }

    #[Test]
    public function it_should_add_description(): void
    {
        $definition = EnumDefinition::string('status', ['on', 'off'])
            ->description('Toggle status');

        $result = $definition->toArray();

        $this->assertEquals('Toggle status', $result['description']);
    }

    #[Test]
    public function it_should_return_empty_references(): void
    {
        $definition = EnumDefinition::string('test', ['a', 'b']);

        $this->assertEquals([], $definition->getReferences());
    }
}
