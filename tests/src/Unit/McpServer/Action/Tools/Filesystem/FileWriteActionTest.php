<?php

declare(strict_types=1);

namespace Tests\Unit\McpServer\Action\Tools\Filesystem;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\Dto\FileWriteRequest;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileWriteAction;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Spiral\Files\FilesInterface;

final class FileWriteActionTest extends TestCase
{
    private FileWriteAction $action;
    private LoggerInterface&MockObject $logger;
    private FilesInterface&MockObject $files;
    private DirectoriesInterface&MockObject $dirs;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->files = $this->createMock(FilesInterface::class);
        $this->dirs = $this->createMock(DirectoriesInterface::class);

        $this->action = new FileWriteAction(
            $this->logger,
            $this->files,
            $this->dirs,
        );
    }

    #[Test]
    public function it_successfully_writes_file_with_content(): void
    {
        // Arrange
        $request = new FileWriteRequest(
            path: 'src/test.txt',
            content: 'Hello, World!',
            createDirectory: true,
        );

        $rootPath = FSPath::create('/project/root');
        $expectedFullPath = '/project/root/src/test.txt';
        $directory = '/project/root/src';

        $this->dirs
            ->expects($this->once())
            ->method('getRootPath')
            ->willReturn($rootPath);

        $this->files
            ->expects($this->once())
            ->method('exists')
            ->with($directory)
            ->willReturn(true);

        $this->files
            ->expects($this->once())
            ->method('write')
            ->with($expectedFullPath, 'Hello, World!')
            ->willReturn(true);

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Processing file-write tool');

        // Act
        $result = ($this->action)($request);

        // Assert
        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertFalse($result->isError);
        $this->assertCount(1, $result->content);

        $content = $result->content[0];
        $this->assertInstanceOf(TextContent::class, $content);
        $this->assertStringContainsString('Successfully wrote 13 bytes', $content->text);
        $this->assertStringContainsString($expectedFullPath, $content->text);
    }

    #[Test]
    public function it_creates_directory_when_requested_and_not_exists(): void
    {
        // Arrange
        $request = new FileWriteRequest(
            path: 'new/folder/test.txt',
            content: 'content',
            createDirectory: true,
        );

        $rootPath = FSPath::create('/project/root');
        $expectedFullPath = '/project/root/new/folder/test.txt';
        $directory = '/project/root/new/folder';

        $this->dirs
            ->expects($this->once())
            ->method('getRootPath')
            ->willReturn($rootPath);

        $this->files
            ->expects($this->once())
            ->method('exists')
            ->with($directory)
            ->willReturn(false);

        $this->files
            ->expects($this->once())
            ->method('ensureDirectory')
            ->with($directory)
            ->willReturn(true);

        $this->files
            ->expects($this->once())
            ->method('write')
            ->with($expectedFullPath, 'content')
            ->willReturn(true);

        // Act
        $result = ($this->action)($request);

        // Assert
        $this->assertFalse($result->isError);
        $this->assertCount(1, $result->content);

        $content = $result->content[0];
        $this->assertInstanceOf(TextContent::class, $content);
        $this->assertStringContainsString('Successfully wrote 7 bytes', $content->text);
    }

    #[Test]
    public function it_returns_error_when_directory_creation_fails(): void
    {
        // Arrange
        $request = new FileWriteRequest(
            path: 'new/folder/test.txt',
            content: 'content',
            createDirectory: true,
        );

        $rootPath = FSPath::create('/project/root');
        $directory = '/project/root/new/folder';

        $this->dirs
            ->expects($this->once())
            ->method('getRootPath')
            ->willReturn($rootPath);

        $this->files
            ->expects($this->once())
            ->method('exists')
            ->with($directory)
            ->willReturn(false);

        $this->files
            ->expects($this->once())
            ->method('ensureDirectory')
            ->with($directory)
            ->willReturn(false);

        // Act
        $result = ($this->action)($request);

        // Assert
        $this->assertTrue($result->isError);
        $this->assertCount(1, $result->content);

        $content = $result->content[0];
        $this->assertInstanceOf(TextContent::class, $content);
        $this->assertStringContainsString('Could not create directory', $content->text);
        $this->assertStringContainsString($directory, $content->text);
    }

    #[Test]
    public function it_returns_error_when_path_is_empty(): void
    {
        // Arrange
        $request = new FileWriteRequest(
            path: '',
            content: 'content',
            createDirectory: true,
        );

        $rootPath = FSPath::create('/project/root');

        // The join will return '/project/root/' when given empty string
        // The dirname of this will be '/project' which doesn't exist
        $this->dirs
            ->expects($this->once())
            ->method('getRootPath')
            ->willReturn($rootPath);

        $this->files
            ->expects($this->once())
            ->method('exists')
            ->with('/project')  // dirname of '/project/root/' 
            ->willReturn(false);

        $this->files
            ->expects($this->once())
            ->method('ensureDirectory')
            ->with('/project')
            ->willReturn(false);

        // Act
        $result = ($this->action)($request);

        // Assert
        $this->assertTrue($result->isError);
        $this->assertCount(1, $result->content);

        $content = $result->content[0];
        $this->assertInstanceOf(TextContent::class, $content);
        $this->assertStringContainsString('Could not create directory', $content->text);
    }

    #[Test]
    public function it_returns_error_when_write_fails(): void
    {
        // Arrange
        $request = new FileWriteRequest(
            path: 'src/test.txt',
            content: 'content',
        );

        $rootPath = FSPath::create('/project/root');
        $expectedFullPath = '/project/root/src/test.txt';
        $directory = '/project/root/src';

        $this->dirs
            ->expects($this->once())
            ->method('getRootPath')
            ->willReturn($rootPath);

        $this->files
            ->expects($this->once())
            ->method('exists')
            ->with($directory)
            ->willReturn(true);

        $this->files
            ->expects($this->once())
            ->method('write')
            ->with($expectedFullPath, 'content')
            ->willReturn(false);

        // Act
        $result = ($this->action)($request);

        // Assert
        $this->assertTrue($result->isError);
        $this->assertCount(1, $result->content);

        $content = $result->content[0];
        $this->assertInstanceOf(TextContent::class, $content);
        $this->assertStringContainsString('Could not write to file', $content->text);
        $this->assertStringContainsString($expectedFullPath, $content->text);
    }

    #[Test]
    public function it_handles_exceptions_gracefully(): void
    {
        // Arrange
        $request = new FileWriteRequest(
            path: 'src/test.txt',
            content: 'content',
        );

        $rootPath = FSPath::create('/project/root');
        $expectedFullPath = '/project/root/src/test.txt';
        $exception = new \RuntimeException('File system error');

        $this->dirs
            ->expects($this->once())
            ->method('getRootPath')
            ->willReturn($rootPath);

        $this->files
            ->expects($this->once())
            ->method('exists')
            ->will($this->throwException($exception));

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Error writing file', [
                'path' => $expectedFullPath,
                'error' => 'File system error',
            ]);

        // Act
        $result = ($this->action)($request);

        // Assert
        $this->assertTrue($result->isError);
        $this->assertCount(1, $result->content);

        $content = $result->content[0];
        $this->assertInstanceOf(TextContent::class, $content);
        $this->assertEquals('Error: File system error', $content->text);
    }

    #[Test]
    public function it_works_without_directory_creation_when_disabled(): void
    {
        // Arrange
        $request = new FileWriteRequest(
            path: 'existing/test.txt',
            content: 'content',
            createDirectory: false,
        );

        $rootPath = FSPath::create('/project/root');
        $expectedFullPath = '/project/root/existing/test.txt';

        $this->dirs
            ->expects($this->once())
            ->method('getRootPath')
            ->willReturn($rootPath);

        // Should not call exists for directory check when createDirectory is false
        $this->files
            ->expects($this->never())
            ->method('exists');

        $this->files
            ->expects($this->never())
            ->method('ensureDirectory');

        $this->files
            ->expects($this->once())
            ->method('write')
            ->with($expectedFullPath, 'content')
            ->willReturn(true);

        // Act
        $result = ($this->action)($request);

        // Assert
        $this->assertFalse($result->isError);
        $this->assertCount(1, $result->content);

        $content = $result->content[0];
        $this->assertInstanceOf(TextContent::class, $content);
        $this->assertStringContainsString('Successfully wrote 7 bytes', $content->text);
    }

    /**
     * @return array<string, array{string, string, bool, string}>
     */
    public static function contentLengthProvider(): array
    {
        return [
            'empty content' => ['', 'test.txt', false, 'Successfully wrote 0 bytes'],
            'short content' => ['Hi', 'test.txt', false, 'Successfully wrote 2 bytes'],
            'unicode content' => ['Hello 🌍', 'test.txt', false, 'Successfully wrote 10 bytes'],
            'multiline content' => ["Line 1\nLine 2\nLine 3", 'test.txt', false, 'Successfully wrote 20 bytes'],
        ];
    }

    #[Test]
    #[DataProvider('contentLengthProvider')]
    public function it_reports_correct_byte_count_for_different_content(
        string $content,
        string $path,
        bool $createDirectory,
        string $expectedMessage,
    ): void {
        // Arrange
        $request = new FileWriteRequest(
            path: $path,
            content: $content,
            createDirectory: $createDirectory,
        );

        $rootPath = FSPath::create('/project/root');
        $expectedFullPath = '/project/root/' . $path;

        $this->dirs
            ->expects($this->once())
            ->method('getRootPath')
            ->willReturn($rootPath);

        // Don't check for directory existence when createDirectory is false
        if ($createDirectory) {
            $directory = \dirname($expectedFullPath);
            $this->files
                ->expects($this->once())
                ->method('exists')
                ->with($directory)
                ->willReturn(true);
        } else {
            $this->files
                ->expects($this->never())
                ->method('exists');
        }

        $this->files
            ->expects($this->once())
            ->method('write')
            ->with($expectedFullPath, $content)
            ->willReturn(true);

        // Act
        $result = ($this->action)($request);

        // Assert
        $this->assertFalse($result->isError);
        $resultContent = $result->content[0];
        $this->assertInstanceOf(TextContent::class, $resultContent);
        $this->assertStringContainsString($expectedMessage, $resultContent->text);
    }

    #[Test]
    public function it_logs_info_when_processing_tool(): void
    {
        // Arrange
        $request = new FileWriteRequest(
            path: 'test.txt',
            content: 'test content',
            createDirectory: false,
        );

        $rootPath = FSPath::create('/project/root');

        $this->dirs->method('getRootPath')->willReturn($rootPath);
        $this->files->method('write')->willReturn(true);

        // Act & Assert
        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Processing file-write tool');

        ($this->action)($request);
    }

    #[Test]
    public function it_handles_null_coalescing_on_path(): void
    {
        // This tests the ?? '' in the original code path logic
        $request = new FileWriteRequest(
            path: 'test.txt',
            content: 'content',
        );

        $rootPath = FSPath::create('/project/root');

        $this->dirs
            ->expects($this->once())
            ->method('getRootPath')
            ->willReturn($rootPath);

        $this->files
            ->expects($this->once())
            ->method('exists')
            ->with('/project/root')
            ->willReturn(true);

        $this->files
            ->expects($this->once())
            ->method('write')
            ->with('/project/root/test.txt', 'content')
            ->willReturn(true);

        $result = ($this->action)($request);

        $this->assertFalse($result->isError);
    }

    #[Test]
    public function it_converts_path_to_string_correctly(): void
    {
        // Arrange
        $request = new FileWriteRequest(
            path: 'src/nested/file.php',
            content: '<?php echo "test";',
            createDirectory: true,
        );

        $rootPath = FSPath::create('/var/www/project');
        $expectedFullPath = '/var/www/project/src/nested/file.php';
        $expectedDirectory = '/var/www/project/src/nested';

        $this->dirs
            ->expects($this->once())
            ->method('getRootPath')
            ->willReturn($rootPath);

        $this->files
            ->expects($this->once())
            ->method('exists')
            ->with($expectedDirectory)
            ->willReturn(true);

        $this->files
            ->expects($this->once())
            ->method('write')
            ->with($expectedFullPath, '<?php echo "test";')
            ->willReturn(true);

        // Act
        $result = ($this->action)($request);

        // Assert
        $this->assertFalse($result->isError);
        $content = $result->content[0];
        $this->assertInstanceOf(TextContent::class, $content);
        $this->assertStringContainsString('Successfully wrote 18 bytes', $content->text);
        $this->assertStringContainsString($expectedFullPath, $content->text);
    }
}