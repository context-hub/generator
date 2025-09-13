<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem;

use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\Dto\FileReadRequest;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Psr\Log\LoggerInterface;
use Spiral\Files\Exception\FilesException;
use Spiral\Files\FilesInterface;

#[Tool(
    name: 'file-read',
    description: 'Read content from a file within the project directory structure',
    title: 'File Read',
)]
#[InputSchema(class: FileReadRequest::class)]
final readonly class FileReadAction
{
    public function __construct(
        private LoggerInterface $logger,
        private FilesInterface $files,
        private DirectoriesInterface $dirs,
    ) {}

    #[Post(path: '/tools/call/file-read', name: 'tools.file-read')]
    public function __invoke(FileReadRequest $request): CallToolResult
    {
        $this->logger->info('Processing file-read tool');

        // Get params from the parsed body for POST requests
        $path = (string) $this->dirs->getRootPath()->join($request->path);

        if (empty($path)) {
            return new CallToolResult([
                new TextContent(
                    text: 'Error: Missing path parameter',
                ),
            ], isError: true);
        }

        try {
            if (!$this->files->exists($path)) {
                return new CallToolResult([
                    new TextContent(
                        text: \sprintf("Error: File '%s' does not exist", $path),
                    ),
                ], isError: true);
            }

            if (\is_dir($path)) {
                return new CallToolResult([
                    new TextContent(
                        text: \sprintf("Error: '%s' is a directory", $path),
                    ),
                ], isError: true);
            }

            try {
                $content = $this->files->read($path);
            } catch (FilesException) {
                return new CallToolResult([
                    new TextContent(
                        text: \sprintf("Error: Could not read file '%s'", $path),
                    ),
                ], isError: true);
            }

            return new CallToolResult([
                new TextContent(
                    text: $content,
                ),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Error reading file', [
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
