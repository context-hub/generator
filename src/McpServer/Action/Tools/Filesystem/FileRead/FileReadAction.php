<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileRead;

use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileRead\Dto\FileReadRequest;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use PhpMcp\Schema\Result\CallToolResult;
use Psr\Log\LoggerInterface;

/**
 * MCP tool for reading file content with support for single and multi-file requests.
 */
#[Tool(
    name: 'file-read',
    description: 'Read content from one or more files within the project directory structure',
    title: 'File Read',
)]
#[InputSchema(class: FileReadRequest::class)]
final readonly class FileReadAction
{
    public function __construct(
        private FileReadHandler $fileHandler,
        private MultiFileReadHandler $multiFileHandler,
        private LoggerInterface $logger,
    ) {}

    #[Post(path: '/tools/call/file-read', name: 'tools.file-read')]
    public function __invoke(FileReadRequest $request): CallToolResult
    {
        $paths = $request->getAllPaths();

        $this->logger->info('Processing file-read tool', [
            'pathCount' => \count($paths),
            'isSingleFile' => $request->isSingleFileRequest(),
            'hasLineRange' => $request->hasLineRange(),
        ]);

        // Validate at least one path is provided
        if (empty($paths)) {
            return ToolResult::error('Missing path parameter. Provide either "path" or "paths".');
        }

        // Line range only applies to single file requests
        if ($request->hasLineRange() && !$request->isSingleFileRequest()) {
            return ToolResult::error('Line range (startLine/endLine) only applies to single file requests.');
        }

        try {
            // Single file request - backward compatible response
            if ($request->isSingleFileRequest()) {
                return $this->handleSingleFile(
                    $paths[0],
                    $request->encoding,
                    $request->startLine,
                    $request->endLine,
                );
            }

            // Multi-file request
            return $this->handleMultipleFiles($paths, $request->encoding);
        } catch (\Throwable $e) {
            $this->logger->error('Error reading files', [
                'paths' => $paths,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error($e->getMessage());
        }
    }

    /**
     * Handle single file request (backward compatible).
     * Returns raw file content on success, with line numbers if partial read.
     */
    private function handleSingleFile(
        string $path,
        string $encoding,
        ?int $startLine = null,
        ?int $endLine = null,
    ): CallToolResult {
        $result = $this->fileHandler->read($path, $encoding, $startLine, $endLine);

        if ($result->success) {
            // If partial read, format with line numbers and metadata
            if ($result->isPartial()) {
                return ToolResult::text($this->formatPartialContent($result));
            }

            return ToolResult::text($result->content ?? '');
        }

        return ToolResult::error($result->error ?? 'Unknown error');
    }

    /**
     * Format partial file content with line numbers.
     */
    private function formatPartialContent(FileReadResult $result): string
    {
        $lines = \explode("\n", $result->content ?? '');
        $lineNumWidth = \strlen((string) $result->endLine);
        $output = [];

        // Header with file info
        $output[] = \sprintf(
            '=== %s (lines %d-%d of %d) ===',
            $result->path,
            $result->startLine,
            $result->endLine,
            $result->totalLines,
        );
        $output[] = '';

        // Content with line numbers
        $currentLine = $result->startLine;
        foreach ($lines as $line) {
            $output[] = \sprintf(
                '%s | %s',
                \str_pad((string) $currentLine, $lineNumWidth, ' ', STR_PAD_LEFT),
                $line,
            );
            $currentLine++;
        }

        return \implode("\n", $output);
    }

    /**
     * Handle multi-file request.
     * Returns formatted response with all file contents.
     */
    private function handleMultipleFiles(array $paths, string $encoding): CallToolResult
    {
        $results = $this->multiFileHandler->readAll($paths, $encoding);

        // If all files failed, return error
        if ($this->multiFileHandler->allFailed($results)) {
            return ToolResult::error($this->multiFileHandler->getAggregatedError($results));
        }

        // Format and return response
        $response = $this->multiFileHandler->formatResponse($results);

        return ToolResult::text($response);
    }
}
