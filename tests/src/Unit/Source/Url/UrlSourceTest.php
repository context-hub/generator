<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Url;

use Butschster\ContextGenerator\Source\Url\UrlSource;
use Butschster\ContextGenerator\Source\Url\UrlSourceFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(UrlSource::class)]
class UrlSourceTest extends TestCase
{
    private const array SAMPLE_URLS = ['https://example.com', 'https://example.org'];
    private const string SAMPLE_DESCRIPTION = 'Sample URL source';
    private const string SAMPLE_SELECTOR = '.content';
    private const array SAMPLE_HEADERS = ['Authorization' => 'Bearer token123'];
    private const array SAMPLE_TAGS = ['api', 'documentation'];

    private UrlSourceFactory $factory;

    /**
     * @return array<string, array{selector: ?string, expectedHasSelector: bool}>
     */
    public static function provideSelectorData(): array
    {
        return [
            'null selector' => [
                'selector' => null,
                'expectedHasSelector' => false,
            ],
            'empty selector' => [
                'selector' => '',
                'expectedHasSelector' => false,
            ],
            'whitespace selector' => [
                'selector' => '   ',
                'expectedHasSelector' => false,
            ],
            'valid selector' => [
                'selector' => '.content',
                'expectedHasSelector' => true,
            ],
        ];
    }

    #[Test]
    public function it_should_create_url_source_with_required_parameters(): void
    {
        $source = new UrlSource(urls: self::SAMPLE_URLS);

        $this->assertSame(self::SAMPLE_URLS, $source->urls);
        $this->assertEmpty($source->getDescription());
        $this->assertEmpty($source->headers);
        $this->assertNull($source->selector);
        $this->assertEmpty($source->getTags());
    }

    #[Test]
    public function it_should_create_url_source_with_all_parameters(): void
    {
        $source = new UrlSource(
            urls: self::SAMPLE_URLS,
            description: self::SAMPLE_DESCRIPTION,
            headers: self::SAMPLE_HEADERS,
            selector: self::SAMPLE_SELECTOR,
            tags: self::SAMPLE_TAGS,
        );

        $this->assertSame(self::SAMPLE_URLS, $source->urls);
        $this->assertSame(self::SAMPLE_DESCRIPTION, $source->getDescription());
        $this->assertSame(self::SAMPLE_HEADERS, $source->headers);
        $this->assertSame(self::SAMPLE_SELECTOR, $source->selector);
        $this->assertSame(self::SAMPLE_TAGS, $source->getTags());
    }

    #[Test]
    public function it_should_create_from_valid_array(): void
    {
        $data = [
            'urls' => self::SAMPLE_URLS,
            'description' => self::SAMPLE_DESCRIPTION,
            'headers' => self::SAMPLE_HEADERS,
            'selector' => self::SAMPLE_SELECTOR,
            'tags' => self::SAMPLE_TAGS,
        ];

        $source = $this->factory->create(config: $data);

        $this->assertSame(self::SAMPLE_URLS, $source->urls);
        $this->assertSame(self::SAMPLE_DESCRIPTION, $source->getDescription());
        $this->assertSame(self::SAMPLE_HEADERS, $source->headers);
        $this->assertSame(self::SAMPLE_SELECTOR, $source->selector);
        $this->assertSame(self::SAMPLE_TAGS, $source->getTags());
    }

    #[Test]
    public function it_should_create_from_minimal_array(): void
    {
        $data = [
            'urls' => self::SAMPLE_URLS,
        ];

        $source = $this->factory->create(config: $data);

        $this->assertSame(self::SAMPLE_URLS, $source->urls);
        $this->assertEmpty($source->getDescription());
        $this->assertEmpty($source->headers);
        $this->assertNull($source->selector);
        $this->assertEmpty($source->getTags());
    }

    #[Test]
    public function it_should_throw_exception_for_missing_urls(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('URL source must have a "urls" array property');
        $this->factory->create(config: [
            'description' => self::SAMPLE_DESCRIPTION,
        ]);
    }

    #[Test]
    public function it_should_throw_exception_for_invalid_urls_type(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('URL source must have a "urls" array property');

        $this->factory->create(config: [
            'urls' => 'not an array',
        ]);
    }

    #[Test]
    #[DataProvider('provideSelectorData')]
    public function it_should_correctly_determine_if_selector_exists(
        ?string $selector,
        bool $expectedHasSelector,
    ): void {
        $source = new UrlSource(
            urls: self::SAMPLE_URLS,
            selector: $selector,
        );

        $this->assertSame($selector, $source->getSelector());
        $this->assertSame($expectedHasSelector, $source->hasSelector());
    }

    #[Test]
    public function it_should_serialize_to_json_with_all_fields(): void
    {
        $source = new UrlSource(
            urls: self::SAMPLE_URLS,
            description: self::SAMPLE_DESCRIPTION,
            headers: self::SAMPLE_HEADERS,
            selector: self::SAMPLE_SELECTOR,
            tags: self::SAMPLE_TAGS,
        );

        $json = $source->jsonSerialize();

        $this->assertSame('url', $json['type']);
        $this->assertSame(self::SAMPLE_URLS, $json['urls']);
        $this->assertSame(self::SAMPLE_DESCRIPTION, $json['description']);
        $this->assertSame(self::SAMPLE_HEADERS, $json['headers']);
        $this->assertSame(self::SAMPLE_SELECTOR, $json['selector']);
        $this->assertSame(self::SAMPLE_TAGS, $json['tags']);
    }

    #[Test]
    public function it_should_serialize_to_json_with_minimal_fields(): void
    {
        $source = new UrlSource(
            urls: self::SAMPLE_URLS,
        );

        $json = $source->jsonSerialize();

        $this->assertSame('url', $json['type']);
        $this->assertSame(self::SAMPLE_URLS, $json['urls']);
        $this->assertArrayNotHasKey('description', $json);
        $this->assertArrayNotHasKey('headers', $json);
        $this->assertArrayNotHasKey('selector', $json);
        $this->assertArrayNotHasKey('tags', $json);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new UrlSourceFactory(dirs: $this->createDirectories());
    }
}
