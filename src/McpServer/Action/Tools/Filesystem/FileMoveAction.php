<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem;

use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\Dto\FileMoveRequest;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use PhpMcp\Schema\Result\CallToolResult;
use Psr\Log\LoggerInterface;
use Spiral\Files\Exception\FilesException;
use Spiral\Files\FilesInterface;

#[Tool(
    name: 'file-move',
    description: 'Move a file within the project directory structure',
    title: 'File Move',
)]
#[InputSchema(class: FileMoveRequest::class)]
final readonly class FileMoveAction
{
    public function __construct(
        private LoggerInterface $logger,
        private FilesInterface $files,
        private DirectoriesInterface $dirs,
    ) {}

    #[Post(path: '/tools/call/file-move', name: 'tools.file-move')]
    public function __invoke(FileMoveRequest $request): CallToolResult
    {
        $this->logger->info('Processing file-move tool');

        // Get params from the parsed body for POST requests
        $source = (string) $this->dirs->getRootPath()->join($request->source);
        $destination = (string) $this->dirs->getRootPath()->join($request->destination);
        $createDirectory = $request->createDirectory;

        if (empty($source)) {
            return ToolResult::error('Missing source parameter');
        }

        if (empty($destination)) {
            return ToolResult::error('Missing destination parameter');
        }

        try {
            if (!$this->files->exists($source)) {
                return ToolResult::error(\sprintf("Source file '%s' does not exist", $source));
            }

            // Ensure destination directory exists if requested
            if ($createDirectory) {
                $directory = \dirname($destination);
                if (!$this->files->exists($directory)) {
                    if (!$this->files->ensureDirectory($directory)) {
                        return ToolResult::error(\sprintf("Could not create directory '%s'", $directory));
                    }
                }
            }

            // Read source file content
            try {
                $content = $this->files->read($source);
            } catch (FilesException) {
                return ToolResult::error(\sprintf("Could not read source file '%s'", $source));
            }

            // Write to destination
            $writeSuccess = $this->files->write($destination, $content);
            if (!$writeSuccess) {
                return ToolResult::error(\sprintf("Could not write to destination file '%s'", $destination));
            }

            // Delete source file
            $deleteSuccess = $this->files->delete($source);
            if (!$deleteSuccess) {
                // Even if delete fails, the move operation is partially successful
                return ToolResult::text(
                    \sprintf(
                        "Warning: File copied to '%s' but could not delete source file '%s'",
                        $destination,
                        $source,
                    ),
                );
            }

            return ToolResult::text(\sprintf("Successfully moved '%s' to '%s'", $source, $destination));
        } catch (\Throwable $e) {
            $this->logger->error('Error moving file', [
                'source' => $source,
                'destination' => $destination,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error($e->getMessage());
        }
    }
}
