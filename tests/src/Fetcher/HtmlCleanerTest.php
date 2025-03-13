<?php

declare(strict_types=1);

namespace Tests\Fetcher;

use Butschster\ContextGenerator\Lib\Html\HtmlCleaner;
use League\HTMLToMarkdown\HtmlConverter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class HtmlCleanerTest extends TestCase
{
    #[Test]
    public function it_should_clean_html_content(): void
    {
        $html = '<div><h1>Test Title</h1><p>This is a test paragraph</p></div>';
        $expectedMarkdown = "# Test Title\n\nThis is a test paragraph";

        $htmlConverter = $this->createMock(HtmlConverter::class);
        $htmlConverter
            ->expects($this->once())
            ->method('convert')
            ->with($html)
            ->willReturn($expectedMarkdown);

        $cleaner = new HtmlCleaner(htmlConverter: $htmlConverter);
        $result = $cleaner->clean($html);

        $this->assertEquals($expectedMarkdown, $result);
    }

    #[Test]
    public function it_should_handle_empty_html(): void
    {
        $html = '';

        $htmlConverter = $this->createMock(HtmlConverter::class);
        $htmlConverter
            ->expects($this->never())
            ->method('convert');

        $cleaner = new HtmlCleaner(htmlConverter: $htmlConverter);
        $result = $cleaner->clean($html);

        $this->assertEquals('', $result);
    }

    #[Test]
    public function it_should_use_default_html_converter_if_not_provided(): void
    {
        $html = '<div><h1>Test Title</h1><p>This is a test paragraph</p></div>';

        $cleaner = new HtmlCleaner();
        $result = $cleaner->clean($html);

        // Just verify that conversion happens without errors
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }
}
