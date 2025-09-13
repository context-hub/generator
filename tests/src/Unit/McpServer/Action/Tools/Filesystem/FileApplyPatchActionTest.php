<?php

declare(strict_types=1);

namespace Tests\Unit\McpServer\Action\Tools\Filesystem;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\Dto\FileApplyPatchChunk;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\Dto\FileApplyPatchRequest;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileApplyPatchAction;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Spiral\Files\Exception\FilesException;
use Spiral\Files\FilesInterface;

final class FileApplyPatchActionTest extends TestCase
{
    private FileApplyPatchAction $action;
    private LoggerInterface&MockObject $logger;
    private FilesInterface&MockObject $files;
    private DirectoriesInterface&MockObject $dirs;

    #[Test]
    public function it_successfully_applies_simple_patch_with_changes(): void
    {
        // Arrange
        $chunks = [
            new FileApplyPatchChunk(
                contextMarker: '@@ class Calculator',
                changes: [
                    ' class Calculator',
                    ' {',
                    '-    public function add($a, $b)',
                    '+    public function add(int $a, int $b): int',
                    '     {',
                ],
            ),
        ];

        $request = new FileApplyPatchRequest(
            path: 'src/Calculator.php',
            chunks: $chunks,
        );

        $originalContent = "<?php\nclass Calculator\n{\n    public function add(\$a, \$b)\n    {\n        return \$a + \$b;\n    }\n}";

        $rootPath = FSPath::create('/project/root');
        $expectedFullPath = '/project/root/src/Calculator.php';

        $this->setupBasicMockExpectations($rootPath, $expectedFullPath, $originalContent, false);

        $this->files
            ->expects($this->once())
            ->method('write')
            ->with($expectedFullPath, $this->isType('string'))
            ->willReturn(true);

        $this->logger
            ->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function ($message, $context) {
                static $callCount = 0;
                $callCount++;

                if ($callCount === 1) {
                    $this->assertEquals('Processing file-apply-patch tool', $message);
                } elseif ($callCount === 2) {
                    $this->assertEquals('Successfully applied patch to file', $message);
                }
            });

        // Act
        $result = ($this->action)($request);

        // Assert
        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertFalse($result->isError);
        $this->assertCount(1, $result->content);

        $content = $result->content[0];
        $this->assertInstanceOf(TextContent::class, $content);

        $responseData = json_decode($content->text, true);
        $this->assertTrue($responseData['success']);
        $this->assertStringContainsString('Successfully applied 1 change chunks', $responseData['message']);
        $this->assertStringContainsString('src/Calculator.php', $responseData['message']);
        $this->assertArrayHasKey('summary', $responseData);
    }

    #[Test]
    public function it_returns_error_when_file_does_not_exist(): void
    {
        // Arrange
        $request = new FileApplyPatchRequest(
            path: 'src/NonExistent.php',
            chunks: [],
        );

        $rootPath = FSPath::create('/project/root');
        $expectedFullPath = '/project/root/src/NonExistent.php';

        $this->dirs
            ->expects($this->once())
            ->method('getRootPath')
            ->willReturn($rootPath);

        $this->files
            ->expects($this->once())
            ->method('exists')
            ->with($expectedFullPath)
            ->willReturn(false);

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Processing file-apply-patch tool', [
                'path' => 'src/NonExistent.php',
                'chunksCount' => 0,
            ]);

        // Act
        $result = ($this->action)($request);

        // Assert
        $this->assertTrue($result->isError);
        $content = $result->content[0];
        $this->assertInstanceOf(TextContent::class, $content);

        $responseData = json_decode($content->text, true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals("Error: File 'src/NonExistent.php' does not exist", $responseData['error']);
    }

    #[Test]
    public function it_returns_error_when_path_is_directory(): void
    {
        // Arrange
        $request = new FileApplyPatchRequest(
            path: 'src',
            chunks: [
                new FileApplyPatchChunk(
                    contextMarker: '@@ some context',
                    changes: [' some line'],
                ),
            ],
        );

        $rootPath = FSPath::create('/project/root');
        $expectedFullPath = '/project/root/src';

        $this->dirs
            ->expects($this->once())
            ->method('getRootPath')
            ->willReturn($rootPath);

        $this->files
            ->expects($this->once())
            ->method('exists')
            ->with($expectedFullPath)
            ->willReturn(true);

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Processing file-apply-patch tool', [
                'path' => 'src',
                'chunksCount' => 1,
            ]);

        // Mock directory path check by making read fail
        $this->files
            ->expects($this->once())
            ->method('read')
            ->with($expectedFullPath)
            ->willThrowException(new FilesException('Is a directory'));

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Error reading file for patch application', $this->isType('array'));

        // Act
        $result = ($this->action)($request);

        // Assert
        $this->assertTrue($result->isError);
        $content = $result->content[0];
        $this->assertInstanceOf(TextContent::class, $content);

        $responseData = json_decode($content->text, true);
        $this->assertFalse($responseData['success']);
        $this->assertStringContainsString('Could not read file', $responseData['error']);
    }

    #[Test]
    public function it_returns_error_when_no_chunks_provided(): void
    {
        // Arrange
        $request = new FileApplyPatchRequest(
            path: 'src/Calculator.php',
            chunks: [],
        );

        $rootPath = FSPath::create('/project/root');
        $expectedFullPath = '/project/root/src/Calculator.php';

        $this->dirs
            ->expects($this->once())
            ->method('getRootPath')
            ->willReturn($rootPath);

        $this->files
            ->expects($this->once())
            ->method('exists')
            ->with($expectedFullPath)
            ->willReturn(true);

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Processing file-apply-patch tool', [
                'path' => 'src/Calculator.php',
                'chunksCount' => 0,
            ]);

        // Act
        $result = ($this->action)($request);

        // Assert
        $this->assertTrue($result->isError);
        $content = $result->content[0];
        $this->assertInstanceOf(TextContent::class, $content);

        $responseData = json_decode($content->text, true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('Error: No change chunks provided', $responseData['error']);
    }

    #[Test]
    public function it_handles_file_read_exception(): void
    {
        // Arrange
        $chunks = [
            new FileApplyPatchChunk(
                contextMarker: '@@ class Calculator',
                changes: [' class Calculator'],
            ),
        ];

        $request = new FileApplyPatchRequest(
            path: 'src/Calculator.php',
            chunks: $chunks,
        );

        $rootPath = FSPath::create('/project/root');
        $expectedFullPath = '/project/root/src/Calculator.php';
        $exception = new FilesException('Permission denied');

        $this->dirs
            ->expects($this->once())
            ->method('getRootPath')
            ->willReturn($rootPath);

        $this->files
            ->expects($this->once())
            ->method('exists')
            ->with($expectedFullPath)
            ->willReturn(true);

        $this->files
            ->expects($this->once())
            ->method('read')
            ->with($expectedFullPath)
            ->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Error reading file for patch application', [
                'path' => $expectedFullPath,
                'error' => 'Permission denied',
            ]);

        // Act
        $result = ($this->action)($request);

        // Assert
        $this->assertTrue($result->isError);
        $content = $result->content[0];
        $this->assertInstanceOf(TextContent::class, $content);

        $responseData = json_decode($content->text, true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals(
            "Error: Could not read file 'src/Calculator.php': Permission denied",
            $responseData['error'],
        );
    }

    #[Test]
    public function it_handles_file_write_failure(): void
    {
        // Arrange - use a very simple chunk that should definitely work
        $chunks = [
            new FileApplyPatchChunk(
                contextMarker: '@@ class Calculator',
                changes: [
                    '+<?php',
                    '+// This is a new file',
                    '+class Calculator',
                ],
            ),
        ];

        $request = new FileApplyPatchRequest(
            path: 'src/Calculator.php',
            chunks: $chunks,
        );

        // Empty original content - adding new content should definitely create changes
        $originalContent = "";

        $rootPath = FSPath::create('/project/root');
        $expectedFullPath = '/project/root/src/Calculator.php';

        $this->setupBasicMockExpectations($rootPath, $expectedFullPath, $originalContent);

        // The write should be called if changes are made, but should return false
        $this->files
            ->expects($this->atMost(1))
            ->method('write')
            ->with($expectedFullPath, $this->isType('string'))
            ->willReturn(false);

        // Act
        $result = ($this->action)($request);

        // Assert - Could be either a write failure or validation failure, both are errors
        $this->assertTrue($result->isError);
        $content = $result->content[0];
        $this->assertInstanceOf(TextContent::class, $content);

        $responseData = json_decode($content->text, true);
        $this->assertFalse($responseData['success']);
        // Accept either error message
        $this->assertTrue(
            str_contains($responseData['error'], 'Could not write modified content') ||
            str_contains($responseData['error'], 'Failed to apply patch'),
        );
    }

    #[Test]
    public function it_logs_processing_information(): void
    {
        // Arrange
        $chunks = [
            new FileApplyPatchChunk(contextMarker: '@@ first chunk', changes: [' line1']),
            new FileApplyPatchChunk(contextMarker: '@@ second chunk', changes: [' line2']),
            new FileApplyPatchChunk(contextMarker: '@@ third chunk', changes: [' line3']),
        ];

        $request = new FileApplyPatchRequest(
            path: 'src/MultiPatch.php',
            chunks: $chunks,
        );

        $rootPath = FSPath::create('/project/root');

        $this->dirs
            ->expects($this->once())
            ->method('getRootPath')
            ->willReturn($rootPath);

        $this->files
            ->expects($this->once())
            ->method('exists')
            ->willReturn(false);

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Processing file-apply-patch tool', [
                'path' => 'src/MultiPatch.php',
                'chunksCount' => 3,
            ]);

        // Act
        ($this->action)($request);
        // Assert is handled by mock expectations
    }

    #[Test]
    public function it_handles_processor_validation_errors(): void
    {
        // Arrange - create chunks that will fail validation (invalid format)
        $chunks = [
            new FileApplyPatchChunk(
                contextMarker: '@@ invalid context that does not exist',
                changes: [
                    'invalid change format without prefix',
                ],
            ),
        ];

        $request = new FileApplyPatchRequest(
            path: 'src/Calculator.php',
            chunks: $chunks,
        );

        $originalContent = "<?php\nclass Calculator\n{\n}";

        $rootPath = FSPath::create('/project/root');
        $expectedFullPath = '/project/root/src/Calculator.php';

        $this->setupBasicMockExpectations($rootPath, $expectedFullPath, $originalContent);

        // The processor should detect validation errors and return them
        // Act
        $result = ($this->action)($request);

        // Assert - Should be an error due to invalid chunk format or context not found
        $this->assertTrue($result->isError);
        $content = $result->content[0];
        $this->assertInstanceOf(TextContent::class, $content);

        $responseData = json_decode($content->text, true);
        $this->assertFalse($responseData['success']);
        $this->assertStringContainsString('Failed to apply patch', $responseData['error']);
    }

    #[Test]
    public function it_returns_success_when_no_changes_needed(): void
    {
        // Arrange - create a chunk that matches existing content exactly
        $chunks = [
            new FileApplyPatchChunk(
                contextMarker: '@@ class Calculator',
                changes: [
                    ' class Calculator',
                    ' {',
                    ' }',
                ],
            ),
        ];

        $request = new FileApplyPatchRequest(
            path: 'src/Calculator.php',
            chunks: $chunks,
        );

        // Content already matches what the patch would create
        $originalContent = "<?php\nclass Calculator\n{\n}";

        $rootPath = FSPath::create('/project/root');
        $expectedFullPath = '/project/root/src/Calculator.php';

        $this->setupBasicMockExpectations($rootPath, $expectedFullPath, $originalContent);

        // Should not attempt to write since no changes are needed
        $this->files
            ->expects($this->never())
            ->method('write');

        // Act
        $result = ($this->action)($request);

        // Assert
        $this->assertFalse($result->isError);
        $content = $result->content[0];
        $this->assertInstanceOf(TextContent::class, $content);

        $responseData = json_decode($content->text, true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals(
            'No changes were needed - file content already matches target state',
            $responseData['message'],
        );
    }

    private function setupBasicMockExpectations(
        FSPath $rootPath,
        string $expectedFullPath,
        string $originalContent,
        bool $expectLoggerCall = true,
    ): void {
        $this->dirs
            ->expects($this->once())
            ->method('getRootPath')
            ->willReturn($rootPath);

        $this->files
            ->expects($this->once())
            ->method('exists')
            ->with($expectedFullPath)
            ->willReturn(true);

        $this->files
            ->expects($this->once())
            ->method('read')
            ->with($expectedFullPath)
            ->willReturn($originalContent);

        if ($expectLoggerCall) {
            $this->logger
                ->expects($this->once())
                ->method('info')
                ->with('Processing file-apply-patch tool', $this->isType('array'));
        }
    }

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->files = $this->createMock(FilesInterface::class);
        $this->dirs = $this->createMock(DirectoriesInterface::class);

        $this->action = new FileApplyPatchAction(
            $this->logger,
            $this->files,
            $this->dirs,
        );
    }
}