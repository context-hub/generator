<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem;

use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Spiral\Files\FilesInterface;

#[Tool(
    name: 'file-write',
    description: 'Write content to a file. Can create parent directories automatically.',
)]
#[InputSchema(
    name: 'path',
    type: 'string',
    description: 'Relative path to the file (e.g., "src/file.txt"). Path is resolved against project root.',
    required: true,
)]
#[InputSchema(
    name: 'content',
    type: 'string',
    description: 'Content to write',
    required: true,
)]
#[InputSchema(
    name: 'createDirectory',
    type: 'boolean',
    description: 'Create directory if it does not exist',
    default: true,
)]
final readonly class FileWriteAction
{
    public function __construct(
        private LoggerInterface $logger,
        private FilesInterface $files,
        private DirectoriesInterface $dirs,
    ) {}

    #[Post(path: '/tools/call/file-write', name: 'tools.file-write')]
    public function __invoke(ServerRequestInterface $request): CallToolResult
    {
        $this->logger->info('Processing file-write tool');

        // Get params from the parsed body for POST requests
        $parsedBody = $request->getParsedBody();
        $path = (string) $this->dirs->getRootPath()->join($parsedBody['path'] ?? '');
        $content = $parsedBody['content'] ?? '';
        $createDirectory = $parsedBody['createDirectory'] ?? true;

        if (empty($path)) {
            return new CallToolResult([
                new TextContent(
                    text: 'Error: Missing path parameter',
                ),
            ], isError: true);
        }

        try {
            // Ensure directory exists if requested
            if ($createDirectory) {
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

            $success = $this->files->write($path, $content);

            if (!$success) {
                return new CallToolResult([
                    new TextContent(
                        text: \sprintf("Error: Could not write to file '%s'", $path),
                    ),
                ], isError: true);
            }

            return new CallToolResult([
                new TextContent(
                    text: \sprintf("Successfully wrote %d bytes to file '%s'", \strlen($content), $path),
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
