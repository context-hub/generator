<?php

declare(strict_types=1);

namespace Tests\Unit\JsonSchema\Property;

use Butschster\ContextGenerator\JsonSchema\Property\NullProperty;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NullPropertyTest extends TestCase
{
    #[Test]
    public function it_should_create_null_type(): void
    {
        $property = NullProperty::new();

        $this->assertEquals(['type' => 'null'], $property->toArray());
    }

    #[Test]
    public function it_should_return_empty_references(): void
    {
        $property = NullProperty::new();

        $this->assertEquals([], $property->getReferences());
    }
}
