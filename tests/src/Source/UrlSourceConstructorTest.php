<?php

declare(strict_types=1);

namespace Tests\Source;

use Butschster\ContextGenerator\Source\UrlSource;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UrlSourceConstructorTest extends TestCase
{
    #[Test]
    public function it_should_store_constructor_parameters(): void
    {
        $urls = ['https://example.com', 'https://example.org'];
        $description = 'Test description';
        $selector = '.content';

        $source = new UrlSource(
            urls: $urls,
            description: $description,
            selector: $selector,
        );

        $this->assertEquals($urls, $source->urls);
        $this->assertEquals($description, $source->getDescription());
        $this->assertEquals($selector, $source->getSelector());
    }

    #[Test]
    public function it_should_have_default_values(): void
    {
        $urls = ['https://example.com'];
        $source = new UrlSource(urls: $urls);

        $this->assertEquals($urls, $source->urls);
        $this->assertEquals('', $source->getDescription());
        $this->assertNull($source->getSelector());
        $this->assertFalse($source->hasSelector());
    }

    #[Test]
    public function it_should_check_if_selector_exists(): void
    {
        $urls = ['https://example.com'];

        $sourceWithSelector = new UrlSource(urls: $urls, selector: '.content');
        $sourceWithEmptySelector = new UrlSource(urls: $urls, selector: '');
        $sourceWithNullSelector = new UrlSource(urls: $urls, selector: null);

        $this->assertTrue($sourceWithSelector->hasSelector());
        $this->assertFalse($sourceWithEmptySelector->hasSelector());
        $this->assertFalse($sourceWithNullSelector->hasSelector());
    }

    #[Test]
    public function it_should_create_new_instance_with_selector(): void
    {
        $urls = ['https://example.com'];
        $description = 'Test description';

        $originalSource = new UrlSource(urls: $urls, description: $description);
        $newSource = $originalSource->withSelector('.content');

        // Original source should remain unchanged
        $this->assertNull($originalSource->getSelector());

        // New source should have the selector
        $this->assertEquals('.content', $newSource->getSelector());
        $this->assertEquals($urls, $newSource->urls);
        $this->assertEquals($description, $newSource->getDescription());
    }
}