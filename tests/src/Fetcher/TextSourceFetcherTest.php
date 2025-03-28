<?php

declare(strict_types=1);

namespace Tests\Fetcher;

use Butschster\ContextGenerator\Modifier\ModifiersApplier;
use Butschster\ContextGenerator\Source\Text\TextSource;
use Butschster\ContextGenerator\Source\Text\TextSourceFetcher;
use Butschster\ContextGenerator\SourceInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TextSourceFetcherTest extends TestCase
{
    private TextSourceFetcher $fetcher;
    private LoggerInterface $logger;

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
            "</INSTRUCTION>\n" . PHP_EOL . PHP_EOL .
            "------------------------------------------------------------\n\n";

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

    #[Test]
    public function it_should_log_debug_message_when_checking_support(): void
    {
        $source = new TextSource(content: 'Sample content');
        $this->logger
            ->expects($this->once())
            ->method('debug')
            ->with('Checking if source is supported', [
                'sourceType' => $source::class,
                'isSupported' => true,
            ]);

        $this->fetcher->supports($source);
    }

    #[Test]
    public function it_should_log_info_message_when_fetching_content(): void
    {
        $content = "This is sample text content";
        $source = new TextSource(content: $content);

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Fetching text source content', [
                'description' => '',
                'tag' => 'INSTRUCTION',
                'contentLength' => \strlen($content),
            ]);

        $this->fetcher->fetch($source, new ModifiersApplier([]));
    }

    #[Test]
    public function it_should_log_error_message_for_invalid_source_type(): void
    {
        $source = $this->createMock(SourceInterface::class);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Source must be an instance of TextSource', [
                'sourceType' => $source::class,
            ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Source must be an instance of TextSource');

        $this->fetcher->fetch($source, new ModifiersApplier([]));
    }

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->fetcher = new TextSourceFetcher(logger: $this->logger);
    }
}
