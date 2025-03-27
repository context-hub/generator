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

final readonly class FileUpdateLinesAction
{
    public function __construct(
        private LoggerInterface $logger,
        private FilesInterface $files,
        private Directories $dirs,
    ) {}

    #[Post(path: '/tools/call/file-update-lines', name: 'tools.update-lines')]
    public function __invoke(ServerRequestInterface $request): CallToolResult
    {
        $this->logger->info('Processing file-update-lines tool');

        // Get params from the parsed body for POST requests
        $parsedBody = $request->getParsedBody();
        $path = $this->dirs->getFilePath($parsedBody['path'] ?? '');
        $startLine = (int) ($parsedBody['startLine'] ?? 0);
        $endLine = (int) ($parsedBody['endLine'] ?? 0);
        $content = $parsedBody['content'] ?? '';

        if (empty($path)) {
            return new CallToolResult([
                new TextContent(
                    text: 'Error: Missing path parameter',
                ),
            ], isError: true);
        }

        if ($startLine <= 0) {
            return new CallToolResult([
                new TextContent(
                    text: 'Error: startLine must be a positive integer',
                ),
            ], isError: true);
        }

        if ($endLine < $startLine) {
            return new CallToolResult([
                new TextContent(
                    text: 'Error: endLine must be greater than or equal to startLine',
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

            // Read the file content
            $fileContent = $this->files->read($path);

            if ($fileContent === false) {
                return new CallToolResult([
                    new TextContent(
                        text: \sprintf("Error: Could not read file '%s'", $path),
                    ),
                ], isError: true);
            }

            // Split the file into lines
            $lines = \explode("\n", $fileContent);
            $totalLines = \count($lines);

            if ($startLine > $totalLines) {
                return new CallToolResult([
                    new TextContent(
                        text: \sprintf(
                            "Error: startLine (%d) exceeds total number of lines in file (%d)",
                            $startLine,
                            $totalLines,
                        ),
                    ),
                ], isError: true);
            }

            // If endLine exceeds file length, cap it at the last line
            $endLine = \min($endLine, $totalLines);

            // Prepare new content lines
            $newContentLines = \explode("\n", $content);

            // Replace the specified lines
            \array_splice($lines, $startLine - 1, $endLine - $startLine + 1, $newContentLines);

            // Combine the lines back to a string
            $updatedContent = \implode("\n", $lines);

            // Write the updated content
            $success = $this->files->write($path, $updatedContent);

            if (!$success) {
                return new CallToolResult([
                    new TextContent(
                        text: \sprintf("Error: Could not write to file '%s'", $path),
                    ),
                ], isError: true);
            }

            return new CallToolResult([
                new TextContent(
                    text: \sprintf(
                        "Successfully updated lines %d to %d in file '%s'",
                        $startLine,
                        $endLine,
                        $path,
                    ),
                ),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Error updating file lines', [
                'path' => $path,
                'startLine' => $startLine,
                'endLine' => $endLine,
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