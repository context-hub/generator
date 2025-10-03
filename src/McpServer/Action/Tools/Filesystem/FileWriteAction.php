<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem;

use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\Dto\FileWriteRequest;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use PhpMcp\Schema\Result\CallToolResult;
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
        $path = (string) $this->dirs->getRootPath()->join($request->path);

        if (empty($path)) {
            return ToolResult::error(error: 'Missing path parameter');
        }

        try {
            // Ensure directory exists if requested
            if ($request->createDirectory) {
                $directory = \dirname(path: $path);
                if (!$this->files->exists($directory)) {
                    if (!$this->files->ensureDirectory($directory)) {
                        return ToolResult::error(error: \sprintf("Could not create directory '%s'", $directory));
                    }
                }
            }

            if (\is_dir(filename: $path)) {
                return ToolResult::error(error: \sprintf("'%s' is a directory", $path));
            }

            $success = $this->files->write($path, $request->content);

            if (!$success) {
                return ToolResult::error(error: \sprintf("Could not write to file '%s'", $path));
            }

            return ToolResult::text(text: \sprintf("Successfully wrote %d bytes to file '%s'", \strlen(string: $request->content), $path));
        } catch (\Throwable $e) {
            $this->logger->error('Error writing file', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error(error: $e->getMessage());
        }
    }
}
