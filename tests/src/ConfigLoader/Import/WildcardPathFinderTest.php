<?php

declare(strict_types=1);

namespace Tests\ConfigLoader\Import;

use Butschster\ContextGenerator\Config\Import\WildcardPathFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Spiral\Files\FilesInterface;

#[CoversClass(WildcardPathFinder::class)]
final class WildcardPathFinderTest extends TestCase
{
    private FilesInterface|MockObject $files;
    private WildcardPathFinder $pathFinder;

    #[Test]
    public function it_should_handle_non_wildcard_paths(): void
    {
        $path = 'config.json';
        $basePath = '/base/path';
        $absolutePath = '/base/path/config.json';

        // Set up the mock to return true for exists check
        $this->files
            ->expects($this->once())
            ->method('exists')
            ->with($absolutePath)
            ->willReturn(true);

        $result = $this->pathFinder->findMatchingPaths($path, $basePath);

        $this->assertSame([$absolutePath], $result);
    }

    #[Test]
    public function it_should_return_empty_array_for_nonexistent_file(): void
    {
        $path = 'nonexistent.json';
        $basePath = '/base/path';
        $absolutePath = '/base/path/nonexistent.json';

        // Set up the mock to return false for exists check
        $this->files
            ->expects($this->once())
            ->method('exists')
            ->with($absolutePath)
            ->willReturn(false);

        $result = $this->pathFinder->findMatchingPaths($path, $basePath);

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_should_return_empty_array_for_nonexistent_base_directory(): void
    {
        // We'll use a real filesystem function for this test
        $path = '*.json';
        $basePath = '/nonexistent/directory';

        $result = $this->pathFinder->findMatchingPaths($path, $basePath);

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_should_handle_absolute_paths(): void
    {
        $path = '/absolute/path/config.json';
        $basePath = '/base/path';

        // Set up the mock to return true for exists check
        $this->files
            ->expects($this->once())
            ->method('exists')
            ->with($path)
            ->willReturn(true);

        $result = $this->pathFinder->findMatchingPaths($path, $basePath);

        $this->assertSame([$path], $result);
    }

    protected function setUp(): void
    {
        $this->files = $this->createMock(FilesInterface::class);
        $this->pathFinder = new WildcardPathFinder($this->files, $this->logger);
    }
}
