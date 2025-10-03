<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem;

use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\Dto\FileReadRequest;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use PhpMcp\Schema\Result\CallToolResult;
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
            return ToolResult::error(error: 'Missing path parameter');
        }

        try {
            if (!$this->files->exists($path)) {
                return ToolResult::error(error: \sprintf("File '%s' does not exist", $path));
            }

            if (\is_dir(filename: $path)) {
                return ToolResult::error(error: \sprintf("'%s' is a directory", $path));
            }

            try {
                $content = $this->files->read($path);
            } catch (FilesException) {
                return ToolResult::error(error: \sprintf("Could not read file '%s'", $path));
            }

            return ToolResult::text(text: $content);
        } catch (\Throwable $e) {
            $this->logger->error('Error reading file', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error(error: $e->getMessage());
        }
    }
}
