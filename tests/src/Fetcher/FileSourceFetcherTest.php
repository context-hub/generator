<?php

declare(strict_types=1);

namespace Tests\Fetcher;

use Butschster\ContextGenerator\Lib\Finder\FinderInterface;
use Butschster\ContextGenerator\Modifier\ModifiersApplier;
use Butschster\ContextGenerator\Modifier\SourceModifierRegistry;
use Butschster\ContextGenerator\Source\File\FileSourceFetcher;
use Butschster\ContextGenerator\Source\File\FileSource;
use Butschster\ContextGenerator\SourceInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\SplFileInfo;

class FileSourceFetcherTest extends TestCase
{
    private string $basePath = '/test/base/path';
    private SourceModifierRegistry $modifiers;
    private FileSourceFetcher $fetcher;
    private FinderInterface $finder;
    private LoggerInterface $logger;

    #[Test]
    public function it_should_not_support_other_sources(): void
    {
        $source = $this->createMock(SourceInterface::class);
        $this->assertFalse($this->fetcher->supports($source));
    }

    #[Test]
    public function it_should_throw_exception_for_invalid_source_type(): void
    {
        $source = $this->createMock(SourceInterface::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Source must be an instance of FileSource');

        $this->fetcher->fetch($source, new ModifiersApplier([]));
    }

    #[Test]
    public function it_should_support_file_source(): void
    {
        $source = $this->createMock(FileSource::class);
        $this->assertTrue($this->fetcher->supports($source));
    }

    #[Test]
    public function it_should_fetch_file_source_content(): void
    {
        $source = $this->createMock(FileSource::class);
        $source->method('getDescription')->willReturn('Test Description');
        $source->method('in')->willReturn(['/test/path']);
        $source->method('files')->willReturn(['file1.txt', 'file2.txt']);
        $source->method('treeView')->willReturn((object)['enabled' => false, 'getOptions' => []]);

        $file1 = $this->createMock(SplFileInfo::class);
        $file1->method('getPathname')->willReturn('/test/base/path/file1.txt');
        $file1->method('getPath')->willReturn('/test/base/path');
        $file1->method('getFilename')->willReturn('file1.txt');
        $file1->method('getSize')->willReturn(100);
        $file1->method('getContents')->willReturn('Content of file1');

        $file2 = $this->createMock(SplFileInfo::class);
        $file2->method('getPathname')->willReturn('/test/base/path/file2.txt');
        $file2->method('getPath')->willReturn('/test/base/path');
        $file2->method('getFilename')->willReturn('file2.txt');
        $file2->method('getSize')->willReturn(200);
        $file2->method('getContents')->willReturn('Content of file2');

        $this->finder->method('find')->willReturn((object)[
            'count' => 2,
            'files' => [$file1, $file2],
            'treeView' => null,
        ]);

        $content = $this->fetcher->fetch($source, new ModifiersApplier([]));

        $expectedContent = "Test Description\n\nContent of file1\nContent of file2\n";
        $this->assertEquals($expectedContent, $content);
    }

    protected function setUp(): void
    {
        $this->modifiers = new SourceModifierRegistry();
        $this->finder = $this->createMock(FinderInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->fetcher = new FileSourceFetcher(
            basePath: $this->basePath,
            finder: $this->finder,
            logger: $this->logger,
        );
    }
}
