<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem;

use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\Dto\FileRenameRequest;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Psr\Log\LoggerInterface;
use Spiral\Files\FilesInterface;

#[Tool(
    name: 'file-rename',
    description: 'Rename a file within the project directory structure',
    title: 'File Rename',
)]
#[InputSchema(class: FileRenameRequest::class)]
final readonly class FileRenameAction
{
    public function __construct(
        private LoggerInterface $logger,
        private FilesInterface $files,
        private DirectoriesInterface $dirs,
    ) {}

    #[Post(path: '/tools/call/file-rename', name: 'tools.file-rename')]
    public function __invoke(FileRenameRequest $request): CallToolResult
    {
        $this->logger->info('Processing file-rename tool');

        // Get params from the parsed body for POST requests
        $path = (string) $this->dirs->getRootPath()->join($request->path ?? '');
        $newPath = (string) $this->dirs->getRootPath()->join($request->newPath ?? '');

        if (empty($path)) {
            return new CallToolResult([
                new TextContent(
                    text: 'Error: Missing path parameter',
                ),
            ], isError: true);
        }

        if (empty($newPath)) {
            return new CallToolResult([
                new TextContent(
                    text: 'Error: Missing newPath parameter',
                ),
            ], isError: true);
        }

        try {
            if (!$this->files->exists($path)) {
                return new CallToolResult([
                    new TextContent(
                        text: \sprintf("Error: File or directory '%s' does not exist", $path),
                    ),
                ], isError: true);
            }

            if ($this->files->exists($newPath)) {
                return new CallToolResult([
                    new TextContent(
                        text: \sprintf("Error: File or directory '%s' already exists", $newPath),
                    ),
                ], isError: true);
            }

            $success = \rename($path, $newPath);

            if (!$success) {
                return new CallToolResult([
                    new TextContent(
                        text: \sprintf("Error: Could not rename '%s' to '%s'", $path, $newPath),
                    ),
                ], isError: true);
            }

            return new CallToolResult([
                new TextContent(
                    text: \sprintf("Successfully renamed '%s' to '%s'", $path, $newPath),
                ),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Error renaming file', [
                'path' => $path,
                'newPath' => $newPath,
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
