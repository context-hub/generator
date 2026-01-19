<?php

declare(strict_types=1);

namespace Tests\Unit\JsonSchema\Combinator;

use Butschster\ContextGenerator\JsonSchema\Combinator\Conditional;
use Butschster\ContextGenerator\JsonSchema\Property\RefProperty;
use Butschster\ContextGenerator\JsonSchema\Property\ObjectProperty;
use Butschster\ContextGenerator\JsonSchema\Property\StringProperty;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConditionalTest extends TestCase
{
    #[Test]
    public function it_should_create_condition_with_when_shorthand(): void
    {
        $conditional = Conditional::when('type', 'file')
            ->then(RefProperty::to('fileSource'));

        $this->assertEquals([
            'if' => [
                'properties' => [
                    'type' => ['const' => 'file'],
                ],
            ],
            'then' => ['$ref' => '#/definitions/fileSource'],
        ], $conditional->toArray());
    }

    #[Test]
    public function it_should_create_condition_with_custom_if(): void
    {
        $conditional = Conditional::if([
            'properties' => [
                'enabled' => ['const' => true],
            ],
            'required' => ['enabled'],
        ])->then(ObjectProperty::new()->property('config', StringProperty::new()));

        $result = $conditional->toArray();

        $this->assertArrayHasKey('if', $result);
        $this->assertArrayHasKey('then', $result);
        $this->assertEquals(['enabled'], $result['if']['required']);
    }

    #[Test]
    public function it_should_add_else_branch(): void
    {
        $conditional = Conditional::when('type', 'a')
            ->then(RefProperty::to('typeA'))
            ->else(RefProperty::to('typeB'));

        $result = $conditional->toArray();

        $this->assertEquals(['$ref' => '#/definitions/typeA'], $result['then']);
        $this->assertEquals(['$ref' => '#/definitions/typeB'], $result['else']);
    }

    #[Test]
    public function it_should_return_empty_references_without_then_or_else(): void
    {
        $conditional = Conditional::when('type', 'test');

        $this->assertEquals([], $conditional->getReferences());
    }

    #[Test]
    public function it_should_collect_references_from_then(): void
    {
        $conditional = Conditional::when('type', 'file')
            ->then(RefProperty::to('fileSource'));

        $this->assertEquals(['fileSource'], $conditional->getReferences());
    }

    #[Test]
    public function it_should_collect_references_from_else(): void
    {
        $conditional = Conditional::when('type', 'a')
            ->else(RefProperty::to('fallback'));

        $this->assertEquals(['fallback'], $conditional->getReferences());
    }

    #[Test]
    public function it_should_collect_references_from_both_branches(): void
    {
        $conditional = Conditional::when('type', 'a')
            ->then(RefProperty::to('typeA'))
            ->else(RefProperty::to('typeB'));

        $this->assertEquals(['typeA', 'typeB'], $conditional->getReferences());
    }

    #[Test]
    public function it_should_deduplicate_references(): void
    {
        $conditional = Conditional::when('type', 'a')
            ->then(RefProperty::to('same'))
            ->else(RefProperty::to('same'));

        $this->assertEquals(['same'], $conditional->getReferences());
    }
}
