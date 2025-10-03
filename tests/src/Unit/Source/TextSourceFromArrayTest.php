<?php

declare(strict_types=1);

namespace Tests\Unit\Source;

use Butschster\ContextGenerator\Source\Text\TextSource;
use Butschster\ContextGenerator\Source\Text\TextSourceFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TextSourceFromArrayTest extends TestCase
{
    #[Test]
    public function it_should_create_from_array_with_minimal_parameters(): void
    {
        $data = [
            'content' => 'This is some test content',
        ];

        $source = $this->factory->create($data);

        $this->assertEquals($data['content'], $source->content);
        $this->assertEquals('', $source->getDescription());
    }

    #[Test]
    public function it_should_create_from_array_with_all_parameters(): void
    {
        $data = [
            'content' => 'This is some test content',
            'description' => 'Test description',
        ];

        $source = $this->factory->create($data);

        $this->assertEquals($data['content'], $source->content);
        $this->assertEquals($data['description'], $source->getDescription());
    }

    #[Test]
    public function it_should_throw_exception_if_content_is_missing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Text source must have a "content" string property');

        $this->factory->create([]);
    }

    #[Test]
    public function it_should_throw_exception_if_content_is_not_string(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Text source must have a "content" string property');

        $this->factory->create(['content' => 123]);
    }

    #[Test]
    public function it_should_serialize_to_json(): void
    {
        $content = 'This is some test content';
        $description = 'Test description';

        $source = new TextSource(
            content: $content,
            description: $description,
        );

        $expected = [
            'type' => 'text',
            'description' => $description,
            'content' => $content,
            'tag' => 'INSTRUCTION',
        ];

        $this->assertEquals($expected, $source->jsonSerialize());
    }

    #[Test]
    public function it_should_filter_empty_values_in_json_serialization(): void
    {
        $content = 'This is some test content';
        $source = new TextSource(content: $content);

        $expected = [
            'type' => 'text',
            'content' => $content,
            'tag' => 'INSTRUCTION',
        ];

        $serialized = $source->jsonSerialize();

        $this->assertArrayNotHasKey('description', $serialized);
        $this->assertEquals($expected, $serialized);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new TextSourceFactory(dirs: $this->createDirectories());
    }
}
