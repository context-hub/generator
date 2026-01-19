<?php

declare(strict_types=1);

namespace Tests\Unit\JsonSchema\Property;

use Butschster\ContextGenerator\JsonSchema\Property\RefProperty;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RefPropertyTest extends TestCase
{
    #[Test]
    public function it_should_create_ref_to_definition(): void
    {
        $property = RefProperty::to('myDefinition');

        $this->assertEquals([
            '$ref' => '#/definitions/myDefinition',
        ], $property->toArray());
    }

    #[Test]
    public function it_should_create_nullable_ref(): void
    {
        $property = RefProperty::to('myDefinition')->nullable();

        $this->assertEquals([
            'oneOf' => [
                ['$ref' => '#/definitions/myDefinition'],
                ['type' => 'null'],
            ],
        ], $property->toArray());
    }

    #[Test]
    public function it_should_return_reference_name(): void
    {
        $property = RefProperty::to('someDefinition');

        $this->assertEquals(['someDefinition'], $property->getReferences());
    }

    #[Test]
    public function it_should_return_reference_name_when_nullable(): void
    {
        $property = RefProperty::to('someDefinition')->nullable();

        $this->assertEquals(['someDefinition'], $property->getReferences());
    }
}
