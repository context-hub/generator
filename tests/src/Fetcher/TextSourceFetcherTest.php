<?php

declare(strict_types=1);

namespace Tests\Fetcher;

use Butschster\ContextGenerator\Fetcher\TextSourceFetcher;
use Butschster\ContextGenerator\Source\TextSource;
use Butschster\ContextGenerator\SourceInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TextSourceFetcherTest extends TestCase
{
    private TextSourceFetcher $fetcher;

    protected function setUp(): void
    {
        $this->fetcher = new TextSourceFetcher();
    }

    #[Test]
    public function it_should_support_text_source(): void
    {
        $source = new TextSource(content: 'Sample content');
        $this->assertTrue($this->fetcher->supports($source));
    }

    #[Test]
    public function it_should_not_support_other_sources(): void
    {
        $source = $this->createMock(SourceInterface::class);
        $this->assertFalse($this->fetcher->supports($source));
    }

    #[Test]
    public function it_should_fetch_content_from_text_source(): void
    {
        $content = "This is sample text content";
        $source = new TextSource(content: $content);

        $expected = "// TEXT CONTENT:" . PHP_EOL .
            $content . PHP_EOL . PHP_EOL .
            "// END OF TEXT CONTENT" . PHP_EOL .
            '----------------------------------------------------------' . PHP_EOL;

        $this->assertEquals($expected, $this->fetcher->fetch($source));
    }

    #[Test]
    public function it_should_throw_exception_for_invalid_source_type(): void
    {
        $source = $this->createMock(SourceInterface::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Source must be an instance of TextSource');

        $this->fetcher->fetch($source);
    }
}