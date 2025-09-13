<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem;

use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\Dto\FileWriteRequest;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Psr\Log\LoggerInterface;
use Spiral\Files\FilesInterface;

#[Tool(
    name: 'file-write',
    description: 'Write content to a file (mostly new files, use apply-path for updates if possible). Can create parent directories automatically.',
    title: 'File Write',
)]
#[InputSchema(class: FileWriteRequest::class)]
final readonly class FileWriteAction
{
    public function __construct(
        private LoggerInterface $logger,
        private FilesInterface $files,
        private DirectoriesInterface $dirs,
    ) {}

    #[Post(path: '/tools/call/file-write', name: 'tools.file-write')]
    public function __invoke(FileWriteRequest $request): CallToolResult
    {
        $this->logger->info('Processing file-write tool');

        // Get params from the parsed body for POST requests
        $path = (string) $this->dirs->getRootPath()->join($request->path ?? '');

        if (empty($path)) {
            return new CallToolResult([
                new TextContent(
                    text: 'Error: Missing path parameter',
                ),
            ], isError: true);
        }

        try {
            // Ensure directory exists if requested
            if ($request->createDirectory) {
                $directory = \dirname($path);
                if (!$this->files->exists($directory)) {
                    if (!$this->files->ensureDirectory($directory)) {
                        return new CallToolResult([
                            new TextContent(
                                text: \sprintf("Error: Could not create directory '%s'", $directory),
                            ),
                        ], isError: true);
                    }
                }
            }

            if (\is_dir($path)) {
                return new CallToolResult([
                    new TextContent(
                        text: \sprintf("Error: '%s' is a directory", $path),
                    ),
                ], isError: true);
            }

            $success = $this->files->write($path, $request->content);

            if (!$success) {
                return new CallToolResult([
                    new TextContent(
                        text: \sprintf("Error: Could not write to file '%s'", $path),
                    ),
                ], isError: true);
            }

            return new CallToolResult([
                new TextContent(
                    text: \sprintf("Successfully wrote %d bytes to file '%s'", \strlen($request->content), $path),
                ),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Error writing file', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return new CallToolResult([
                new TextContent(
                    text: 'Error: ' . $e->getMessage(),
                ),
            ], isError: true);
        }
    }
}
