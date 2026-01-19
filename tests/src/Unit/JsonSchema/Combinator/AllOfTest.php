<?php

declare(strict_types=1);

namespace Tests\Unit\JsonSchema\Combinator;

use Butschster\ContextGenerator\JsonSchema\Combinator\AllOf;
use Butschster\ContextGenerator\JsonSchema\Property\RefProperty;
use Butschster\ContextGenerator\JsonSchema\Property\ObjectProperty;
use Butschster\ContextGenerator\JsonSchema\Property\StringProperty;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AllOfTest extends TestCase
{
    #[Test]
    public function it_should_create_all_of_with_schemas(): void
    {
        $combinator = AllOf::create(
            RefProperty::to('base'),
            ObjectProperty::new()->property('extra', StringProperty::new()),
        );

        $this->assertEquals([
            'allOf' => [
                ['$ref' => '#/definitions/base'],
                [
                    'type' => 'object',
                    'properties' => [
                        'extra' => ['type' => 'string'],
                    ],
                ],
            ],
        ], $combinator->toArray());
    }

    #[Test]
    public function it_should_return_empty_references_for_inline_schemas(): void
    {
        $combinator = AllOf::create(
            ObjectProperty::new()->property('a', StringProperty::new()),
        );

        $this->assertEquals([], $combinator->getReferences());
    }

    #[Test]
    public function it_should_collect_references_from_schemas(): void
    {
        $combinator = AllOf::create(
            RefProperty::to('base'),
            RefProperty::to('extended'),
        );

        $this->assertEquals(['base', 'extended'], $combinator->getReferences());
    }

    #[Test]
    public function it_should_deduplicate_references(): void
    {
        $combinator = AllOf::create(
            RefProperty::to('same'),
            RefProperty::to('same'),
        );

        $this->assertEquals(['same'], $combinator->getReferences());
    }
}
