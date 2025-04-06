<?php

declare(strict_types=1);

namespace Tests\Unit\Fetcher;

use Butschster\ContextGenerator\Modifier\ModifiersApplier;
use Butschster\ContextGenerator\Source\SourceInterface;
use Butschster\ContextGenerator\Source\Text\TextSource;
use Butschster\ContextGenerator\Source\Text\TextSourceFetcher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TextSourceFetcherTest extends TestCase
{
    private TextSourceFetcher $fetcher;

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

        $expected = "<INSTRUCTION>\n" .
            $content . PHP_EOL .
            "</INSTRUCTION>\n" .
            "------------------------------------------------------------\n";

        $this->assertEquals($expected, $this->fetcher->fetch($source, new ModifiersApplier([])));
    }

    #[Test]
    public function it_should_throw_exception_for_invalid_source_type(): void
    {
        $source = $this->createMock(SourceInterface::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Source must be an instance of TextSource');

        $this->fetcher->fetch($source, new ModifiersApplier([]));
    }

    protected function setUp(): void
    {
        $this->fetcher = new TextSourceFetcher();
    }
}
