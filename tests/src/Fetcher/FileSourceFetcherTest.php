<?php

declare(strict_types=1);

namespace Tests\Fetcher;

use Butschster\ContextGenerator\Fetcher\FileSourceFetcher;
use Butschster\ContextGenerator\Fetcher\FinderInterface;
use Butschster\ContextGenerator\Fetcher\Finder\FinderResult;
use Butschster\ContextGenerator\Source\FileSource;
use Butschster\ContextGenerator\Source\SourceModifierRegistry;
use Butschster\ContextGenerator\SourceInterface;
use Butschster\ContextGenerator\SourceModifierInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;

class FileSourceFetcherTest extends TestCase
{
    private string $basePath = '/test/base/path';
    private SourceModifierRegistry $modifiers;
    private FileSourceFetcher $fetcher;
    private FinderInterface $finder;

    protected function setUp(): void
    {
        $this->modifiers = new SourceModifierRegistry();
        $this->finder = $this->createMock(FinderInterface::class);

        $this->fetcher = new FileSourceFetcher(
            basePath: $this->basePath,
            modifiers: $this->modifiers,
            finder: $this->finder,
        );
    }

    #[Test]
    public function it_should_support_file_source(): void
    {
        $source = $this->createMock(FileSource::class);
        $this->assertTrue($this->fetcher->supports($source));
    }

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

        $this->fetcher->fetch($source);
    }

    #[Test]
    public function it_should_fetch_content_from_file_source(): void
    {
        // Create a real FileSource
        $source = new FileSource(
            sourcePaths: ['/test/base/path/file1.php'],
            filePattern: '*.php',
            showTreeView: false,
        );

        // Create a mock file
        $file = $this->createMock(SplFileInfo::class);
        $file
            ->expects($this->once())
            ->method('getPath')
            ->willReturn('/test/base/path');
        $file
            ->expects($this->any())
            ->method('getFilename')
            ->willReturn('file1.php');
        $file
            ->expects($this->once())
            ->method('getContents')
            ->willReturn('<?php echo "Hello World"; ?>');

        // Create finder result
        $finderResult = new FinderResult(
            files: new \ArrayIterator([$file]),
            treeView: "",
        );

        // Set up the finder mock
        $this->finder
            ->expects($this->once())
            ->method('find')
            ->with($source, $this->basePath)
            ->willReturn($finderResult);

        $result = $this->fetcher->fetch($source);

        $expected = "```\n// Path: file1.php\n<?php echo \"Hello World\"; ?>\n\n```\n";
        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function it_should_include_tree_view_when_requested(): void
    {
        // Create a real FileSource
        $source = new FileSource(
            sourcePaths: ['/test/base/path/file1.php'],
            filePattern: '*.php',
            showTreeView: true,
        );

        // Create a mock file
        $file = $this->createMock(SplFileInfo::class);
        $file
            ->expects($this->once())
            ->method('getPath')
            ->willReturn('/test/base/path');
        $file
            ->expects($this->once())
            ->method('getFilename')
            ->willReturn('file1.php');
        $file
            ->expects($this->once())
            ->method('getContents')
            ->willReturn('<?php echo "Hello World"; ?>');

        // Create finder result with tree view
        $treeView = "└── file1.php\n";
        $finderResult = new FinderResult(
            files: new \ArrayIterator([$file]),
            treeView: $treeView,
        );

        // Set up the finder mock
        $this->finder
            ->expects($this->once())
            ->method('find')
            ->with($source, $this->basePath)
            ->willReturn($finderResult);

        $result = $this->fetcher->fetch($source);

        $expected = "```\n" . $treeView . "```\n\n```\n// Path: file1.php\n<?php echo \"Hello World\"; ?>\n\n```\n";
        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function it_should_apply_modifiers_when_available(): void
    {
        // Create a real FileSource with a modifier
        $source = new FileSource(
            sourcePaths: ['/test/base/path/file1.php'],
            filePattern: '*.php',
            showTreeView: false,
            modifiers: ['modifier1'],
        );

        // Create a mock file
        $file = $this->createMock(SplFileInfo::class);
        $file
            ->expects($this->once())
            ->method('getPath')
            ->willReturn('/test/base/path');
        $file
            ->method('getFilename')
            ->willReturn('file1.php');
        $file
            ->expects($this->once())
            ->method('getContents')
            ->willReturn('<?php echo "Hello World"; ?>');

        // Create finder result
        $finderResult = new FinderResult(
            files: new \ArrayIterator([$file]),
            treeView: "",
        );

        // Set up the finder mock
        $this->finder
            ->expects($this->once())
            ->method('find')
            ->with($source, $this->basePath)
            ->willReturn($finderResult);

        // Create and register a modifier
        $modifier = $this->createMock(SourceModifierInterface::class);
        $modifier
            ->expects($this->once())
            ->method('supports')
            ->with('file1.php')
            ->willReturn(true);
        $modifier
            ->expects($this->once())
            ->method('modify')
            ->with(
                '<?php echo "Hello World"; ?>',
                $this->callback(function ($context) use ($file, $source) {
                    return isset($context['file']) && $context['file'] === $file &&
                        isset($context['source']) && $context['source'] === $source;
                }),
            )
            ->willReturn('<?php echo "Modified Hello World"; ?>');
        $modifier
            ->expects($this->atLeastOnce())
            ->method('getIdentifier')
            ->willReturn('modifier1');

        $this->modifiers->register($modifier);

        $result = $this->fetcher->fetch($source);

        $expected = "```\n// Path: file1.php\n<?php echo \"Modified Hello World\"; ?>\n\n```\n";
        $this->assertEquals($expected, $result);
    }
}