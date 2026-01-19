<?php

declare(strict_types=1);

namespace Tests\Unit\JsonSchema\Combinator;

use Butschster\ContextGenerator\JsonSchema\Combinator\AnyOf;
use Butschster\ContextGenerator\JsonSchema\Property\RefProperty;
use Butschster\ContextGenerator\JsonSchema\Property\StringProperty;
use Butschster\ContextGenerator\JsonSchema\Property\IntegerProperty;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnyOfTest extends TestCase
{
    #[Test]
    public function it_should_create_any_of_with_options(): void
    {
        $combinator = AnyOf::create(
            StringProperty::new(),
            IntegerProperty::new(),
        );

        $this->assertEquals([
            'anyOf' => [
                ['type' => 'string'],
                ['type' => 'integer'],
            ],
        ], $combinator->toArray());
    }

    #[Test]
    public function it_should_return_empty_references_for_simple_types(): void
    {
        $combinator = AnyOf::create(StringProperty::new());

        $this->assertEquals([], $combinator->getReferences());
    }

    #[Test]
    public function it_should_collect_references_from_options(): void
    {
        $combinator = AnyOf::create(
            RefProperty::to('typeA'),
            RefProperty::to('typeB'),
        );

        $this->assertEquals(['typeA', 'typeB'], $combinator->getReferences());
    }

    #[Test]
    public function it_should_deduplicate_references(): void
    {
        $combinator = AnyOf::create(
            RefProperty::to('same'),
            RefProperty::to('same'),
        );

        $this->assertEquals(['same'], $combinator->getReferences());
    }
}
