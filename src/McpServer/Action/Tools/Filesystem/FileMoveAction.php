<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem;

use Butschster\ContextGenerator\Directories;
use Butschster\ContextGenerator\FilesInterface;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final readonly class FileMoveAction
{
    public function __construct(
        private LoggerInterface $logger,
        private FilesInterface $files,
        private Directories $dirs,
    ) {}

    #[Post(path: '/tools/call/file-move', name: 'tools.file-move')]
    public function __invoke(ServerRequestInterface $request): CallToolResult
    {
        $this->logger->info('Processing file-move tool');

        // Get params from the parsed body for POST requests
        $parsedBody = $request->getParsedBody();
        $source = $this->dirs->getFilePath($parsedBody['source'] ?? '');
        $destination = $this->dirs->getFilePath($parsedBody['destination'] ?? '');
        $createDirectory = $parsedBody['createDirectory'] ?? true;

        if (empty($source)) {
            return new CallToolResult([
                new TextContent(
                    text: 'Error: Missing source parameter',
                ),
            ], isError: true);
        }

        if (empty($destination)) {
            return new CallToolResult([
                new TextContent(
                    text: 'Error: Missing destination parameter',
                ),
            ], isError: true);
        }

        try {
            if (!$this->files->exists($source)) {
                return new CallToolResult([
                    new TextContent(
                        text: \sprintf("Error: Source file '%s' does not exist", $source),
                    ),
                ], isError: true);
            }

            // Ensure destination directory exists if requested
            if ($createDirectory) {
                $directory = \dirname($destination);
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

            // Read source file content
            $content = $this->files->read($source);
            if ($content === false) {
                return new CallToolResult([
                    new TextContent(
                        text: \sprintf("Error: Could not read source file '%s'", $source),
                    ),
                ], isError: true);
            }

            // Write to destination
            $writeSuccess = $this->files->write($destination, $content);
            if (!$writeSuccess) {
                return new CallToolResult([
                    new TextContent(
                        text: \sprintf("Error: Could not write to destination file '%s'", $destination),
                    ),
                ], isError: true);
            }

            // Delete source file
            $deleteSuccess = $this->files->delete($source);
            if (!$deleteSuccess) {
                // Even if delete fails, the move operation is partially successful
                return new CallToolResult([
                    new TextContent(
                        text: \sprintf(
                            "Warning: File copied to '%s' but could not delete source file '%s'",
                            $destination,
                            $source,
                        ),
                    ),
                ]);
            }

            return new CallToolResult([
                new TextContent(
                    text: \sprintf("Successfully moved '%s' to '%s'", $source, $destination),
                ),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Error moving file', [
                'source' => $source,
                'destination' => $destination,
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
