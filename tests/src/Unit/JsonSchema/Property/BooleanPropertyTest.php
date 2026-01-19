<?php

declare(strict_types=1);

namespace Tests\Unit\JsonSchema\Property;

use Butschster\ContextGenerator\JsonSchema\Property\BooleanProperty;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BooleanPropertyTest extends TestCase
{
    #[Test]
    public function it_should_create_basic_boolean_type(): void
    {
        $property = BooleanProperty::new();

        $this->assertEquals(['type' => 'boolean'], $property->toArray());
    }

    #[Test]
    public function it_should_add_description(): void
    {
        $property = BooleanProperty::new()->description('Enabled flag');

        $this->assertEquals([
            'type' => 'boolean',
            'description' => 'Enabled flag',
        ], $property->toArray());
    }

    #[Test]
    public function it_should_add_default_value(): void
    {
        $property = BooleanProperty::new()->default(true);

        $this->assertEquals([
            'type' => 'boolean',
            'default' => true,
        ], $property->toArray());
    }

    #[Test]
    public function it_should_return_empty_references(): void
    {
        $property = BooleanProperty::new();

        $this->assertEquals([], $property->getReferences());
    }
}
