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
use Spiral\Files\Exception\FilesException;
use Spiral\Files\FilesInterface;

#[Tool(
    name: 'file-read',
    description: 'Read content from a file within the project directory structure',
)]
#[InputSchema(
    name: 'path',
    type: 'string',
    description: 'Path to the file, relative to project root. Only files within project directory can be accessed.',
    required: true,
)]
#[InputSchema(
    name: 'encoding',
    type: 'string',
    description: 'File encoding (default: utf-8)',
    default: 'utf-8',
)]
final readonly class FileReadAction
{
    public function __construct(
        private LoggerInterface $logger,
        private FilesInterface $files,
        private DirectoriesInterface $dirs,
    ) {}

    #[Post(path: '/tools/call/file-read', name: 'tools.file-read')]
    public function __invoke(ServerRequestInterface $request): CallToolResult
    {
        $this->logger->info('Processing file-read tool');

        // Get params from the parsed body for POST requests
        $parsedBody = $request->getParsedBody();
        $path = (string) $this->dirs->getRootPath()->join($parsedBody['path'] ?? '');
        $parsedBody['encoding'] ?? 'utf-8';

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
