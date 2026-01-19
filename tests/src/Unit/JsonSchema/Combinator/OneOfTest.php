<?php

declare(strict_types=1);

namespace Tests\Unit\JsonSchema\Combinator;

use Butschster\ContextGenerator\JsonSchema\Combinator\OneOf;
use Butschster\ContextGenerator\JsonSchema\Property\RefProperty;
use Butschster\ContextGenerator\JsonSchema\Property\StringProperty;
use Butschster\ContextGenerator\JsonSchema\Property\IntegerProperty;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OneOfTest extends TestCase
{
    #[Test]
    public function it_should_create_one_of_with_options(): void
    {
        $combinator = OneOf::create(
            StringProperty::new(),
            IntegerProperty::new(),
        );

        $this->assertEquals([
            'oneOf' => [
                ['type' => 'string'],
                ['type' => 'integer'],
            ],
        ], $combinator->toArray());
    }

    #[Test]
    public function it_should_add_options_fluently(): void
    {
        $combinator = OneOf::create()
            ->add(StringProperty::new())
            ->add(IntegerProperty::new());

        $this->assertEquals([
            'oneOf' => [
                ['type' => 'string'],
                ['type' => 'integer'],
            ],
        ], $combinator->toArray());
    }

    #[Test]
    public function it_should_return_empty_references_for_simple_types(): void
    {
        $combinator = OneOf::create(
            StringProperty::new(),
            IntegerProperty::new(),
        );

        $this->assertEquals([], $combinator->getReferences());
    }

    #[Test]
    public function it_should_collect_references_from_options(): void
    {
        $combinator = OneOf::create(
            RefProperty::to('typeA'),
            RefProperty::to('typeB'),
        );

        $this->assertEquals(['typeA', 'typeB'], $combinator->getReferences());
    }

    #[Test]
    public function it_should_deduplicate_references(): void
    {
        $combinator = OneOf::create(
            RefProperty::to('same'),
            RefProperty::to('same'),
        );

        $this->assertEquals(['same'], $combinator->getReferences());
    }
}
