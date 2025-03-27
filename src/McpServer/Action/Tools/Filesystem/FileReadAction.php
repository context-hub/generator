<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem;

use Butschster\ContextGenerator\Directories;
use Butschster\ContextGenerator\FilesInterface;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final readonly class FileReadAction
{
    public function __construct(
        private LoggerInterface $logger,
        private FilesInterface $files,
        private Directories $dirs,
    ) {}

    public function __invoke(ServerRequestInterface $request): CallToolResult
    {
        $this->logger->info('Processing file-read tool');

        // Get params from the parsed body for POST requests
        $parsedBody = $request->getParsedBody();
        $path = $this->dirs->getFilePath($parsedBody['path'] ?? '');

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

            $content = $this->files->read($path);

            if ($content === false) {
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