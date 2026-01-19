<?php

declare(strict_types=1);

namespace Tests\Unit\McpServer\Action\Tools\Filesystem\FileWrite;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\Config\Exclude\ExcludeRegistryInterface;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\Dto\FileWriteRequest;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileWrite\FileWriteHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Spiral\Core\Attribute\Proxy;
use Spiral\Files\FilesInterface;

final class FileWriteHandlerTest extends TestCase
{
    private FilesInterface $files;

    #[Proxy]
    private DirectoriesInterface $dirs;

    private ExcludeRegistryInterface $excludeRegistry;
    private LoggerInterface $logger;
    private FileWriteHandler $handler;
    private FSPath $rootPath;

    #[Test]
    public function it_returns_error_when_path_is_excluded(): void
    {
        $request = new FileWriteRequest('.env', 'secret data', false);
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
        $request = new FileWriteRequest('secrets/password.txt', 'password123', false);
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

    #[Test]
    public function it_proceeds_when_path_is_not_excluded(): void
    {
        $path = 'src/Example.php';
        $content = '<?php echo "Hello";';
        $fullPath = '/project/src/Example.php';

        $request = new FileWriteRequest($path, $content, false);

        $this->excludeRegistry
            ->expects($this->once())
            ->method('shouldExclude')
            ->with($path)
            ->willReturn(false);

        $this->files
            ->expects($this->once())
            ->method('write')
            ->with($fullPath, $content)
            ->willReturn(true);

        $result = $this->handler->handle($request);

        $this->assertTrue($result->success);
        $this->assertEquals(\strlen($content), $result->bytesWritten);
        $this->assertEquals($path, $result->path);
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

        $this->handler = new FileWriteHandler(
            $this->files,
            $this->dirs,
            $this->excludeRegistry,
            $this->logger,
        );
    }
}
