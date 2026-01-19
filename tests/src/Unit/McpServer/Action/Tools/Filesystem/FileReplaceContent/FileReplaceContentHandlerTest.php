<?php

declare(strict_types=1);

namespace Tests\Unit\McpServer\Action\Tools\Filesystem\FileReplaceContent;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\Config\Exclude\ExcludeRegistryInterface;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileReplaceContent\FileReplaceContentHandler;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileReplaceContent\LineEndingNormalizer;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileReplaceContent\Dto\FileReplaceContentRequest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Spiral\Core\Attribute\Proxy;
use Spiral\Files\FilesInterface;

final class FileReplaceContentHandlerTest extends TestCase
{
    private FilesInterface $files;

    #[Proxy]
    private DirectoriesInterface $dirs;

    private ExcludeRegistryInterface $excludeRegistry;
    private LoggerInterface $logger;
    private FileReplaceContentHandler $handler;
    private FSPath $rootPath;

    #[Test]
    public function it_returns_error_when_path_is_excluded(): void
    {
        $request = new FileReplaceContentRequest('.env', 'old', 'new');

        $this->excludeRegistry
            ->expects($this->once())
            ->method('shouldExclude')
            ->with('.env')
            ->willReturn(true);

        $result = $this->handler->handle($request);

        $this->assertFalse($result->success);
        $this->assertStringContainsString(
            "Path '.env' is excluded by project configuration",
            $result->error ?? '',
        );
    }

    #[Test]
    public function it_returns_error_when_path_matches_exclude_pattern(): void
    {
        $request = new FileReplaceContentRequest('secrets/password.txt', 'old-password', 'new-password');

        $this->excludeRegistry
            ->expects($this->once())
            ->method('shouldExclude')
            ->with('secrets/password.txt')
            ->willReturn(true);

        $result = $this->handler->handle($request);

        $this->assertFalse($result->success);
        $this->assertStringContainsString(
            "Path 'secrets/password.txt' is excluded by project configuration",
            $result->error ?? '',
        );
    }

    protected function setUp(): void
    {
        $this->files = $this->createMock(FilesInterface::class);
        $this->dirs = $this->createMock(DirectoriesInterface::class);
        $this->excludeRegistry = $this->createMock(ExcludeRegistryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->rootPath = FSPath::create('/project');

        $this->dirs
            ->method('getRootPath')
            ->willReturn($this->rootPath);

        // Create a real LineEndingNormalizer instance since it's final
        $normalizer = new LineEndingNormalizer();

        $this->handler = new FileReplaceContentHandler(
            $this->files,
            $this->dirs,
            $normalizer,
            $this->excludeRegistry,
            $this->logger,
        );
    }
}
