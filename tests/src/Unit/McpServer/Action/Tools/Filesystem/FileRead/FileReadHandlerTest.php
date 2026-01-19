<?php

declare(strict_types=1);

namespace Tests\Unit\McpServer\Action\Tools\Filesystem\FileRead;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\Config\Exclude\ExcludeRegistryInterface;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileRead\FileReadHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Spiral\Core\Attribute\Proxy;
use Spiral\Files\FilesInterface;

final class FileReadHandlerTest extends TestCase
{
    private FilesInterface $files;

    #[Proxy]
    private DirectoriesInterface $dirs;

    private ExcludeRegistryInterface $excludeRegistry;
    private LoggerInterface $logger;
    private FileReadHandler $handler;
    private FSPath $rootPath;

    #[Test]
    public function it_returns_error_when_path_is_excluded(): void
    {
        $path = '.env';
        $this->excludeRegistry
            ->expects($this->once())
            ->method('shouldExclude')
            ->with($path)
            ->willReturn(true);

        $result = $this->handler->read($path);

        $this->assertFalse($result->success);
        $this->assertStringContainsString(
            "Path '.env' is excluded by project configuration",
            $result->error ?? '',
        );
    }

    #[Test]
    public function it_returns_error_when_path_matches_exclude_pattern(): void
    {
        $path = 'secrets/password.txt';
        $this->excludeRegistry
            ->expects($this->once())
            ->method('shouldExclude')
            ->with($path)
            ->willReturn(true);

        $result = $this->handler->read($path);

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
        $fullPath = '/project/src/Example.php';
        $content = '<?php echo "Hello";';

        $this->excludeRegistry
            ->expects($this->once())
            ->method('shouldExclude')
            ->with($path)
            ->willReturn(false);

        $this->files
            ->expects($this->once())
            ->method('exists')
            ->with($fullPath)
            ->willReturn(true);

        $this->files
            ->expects($this->any())
            ->method('isDirectory')
            ->willReturn(false);

        $this->files
            ->expects($this->once())
            ->method('size')
            ->with($fullPath)
            ->willReturn(\strlen($content));

        $this->files
            ->expects($this->once())
            ->method('read')
            ->with($fullPath)
            ->willReturn($content);

        $result = $this->handler->read($path);

        $this->assertTrue($result->success);
        $this->assertEquals($content, $result->content);
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

        $this->handler = new FileReadHandler(
            $this->files,
            $this->dirs,
            $this->excludeRegistry,
            $this->logger,
        );
    }
}
