<?php

declare(strict_types=1);

namespace Tests\Source;

use Butschster\ContextGenerator\Source\UrlSource;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UrlSourceFromArrayTest extends TestCase
{
    #[Test]
    public function it_should_create_from_array_with_minimal_parameters(): void
    {
        $data = [
            'urls' => ['https://example.com', 'https://example.org'],
        ];

        $source = UrlSource::fromArray($data);

        $this->assertEquals($data['urls'], $source->urls);
        $this->assertEquals('', $source->getDescription());
        $this->assertNull($source->getSelector());
    }

    #[Test]
    public function it_should_create_from_array_with_all_parameters(): void
    {
        $data = [
            'urls' => ['https://example.com', 'https://example.org'],
            'description' => 'Test description',
            'selector' => '.content',
        ];

        $source = UrlSource::fromArray($data);

        $this->assertEquals($data['urls'], $source->urls);
        $this->assertEquals($data['description'], $source->getDescription());
        $this->assertEquals($data['selector'], $source->getSelector());
    }

    #[Test]
    public function it_should_throw_exception_if_urls_is_missing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('URL source must have a "urls" array property');

        UrlSource::fromArray([]);
    }

    #[Test]
    public function it_should_throw_exception_if_urls_is_not_array(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('URL source must have a "urls" array property');

        UrlSource::fromArray(['urls' => 'https://example.com']);
    }

    #[Test]
    public function it_should_serialize_to_json(): void
    {
        $urls = ['https://example.com', 'https://example.org'];
        $description = 'Test description';
        $selector = '.content';

        $source = new UrlSource(
            urls: $urls,
            description: $description,
            selector: $selector,
        );

        $expected = [
            'type' => 'url',
            'urls' => $urls,
            'description' => $description,
            'selector' => $selector,
        ];

        $this->assertEquals($expected, $source->jsonSerialize());
    }

    #[Test]
    public function it_should_filter_empty_values_in_json_serialization(): void
    {
        $urls = ['https://example.com'];
        $source = new UrlSource(urls: $urls);

        $expected = [
            'type' => 'url',
            'urls' => $urls,
        ];

        $serialized = $source->jsonSerialize();

        $this->assertArrayNotHasKey('description', $serialized);
        $this->assertArrayNotHasKey('selector', $serialized);
        $this->assertEquals($expected, $serialized);
    }
}