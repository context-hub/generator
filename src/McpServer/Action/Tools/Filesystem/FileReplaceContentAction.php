<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem;

use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\Dto\FileReplaceContentRequest;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use PhpMcp\Schema\Result\CallToolResult;
use Psr\Log\LoggerInterface;
use Spiral\Files\FilesInterface;

#[Tool(
    name: 'file-replace-content',
    description: 'Replace a unique occurrence of text in a file with exact matching.',
    title: 'File Replace Content',
)]
#[InputSchema(class: FileReplaceContentRequest::class)]
final readonly class FileReplaceContentAction
{
    public function __construct(
        private LoggerInterface $logger,
        private FilesInterface $files,
        private DirectoriesInterface $dirs,
    ) {}

    #[Post(path: '/tools/call/file-replace-content', name: 'tools.file-replace-content')]
    public function __invoke(FileReplaceContentRequest $request): CallToolResult
    {
        $this->logger->info('Processing file-replace-content tool', [
            'path' => $request->path,
            'searchLength' => \strlen($request->search),
            'replaceLength' => \strlen($request->replace),
        ]);

        $path = (string) $this->dirs->getRootPath()->join($request->path);

        if (empty($request->path)) {
            return ToolResult::error('Missing path parameter');
        }

        if (empty($request->search)) {
            return ToolResult::error('Missing search parameter - cannot replace empty content');
        }

        try {
            // Validate file exists and is readable
            if (!$this->files->exists($path)) {
                return ToolResult::error(\sprintf("File '%s' does not exist", $request->path));
            }

            if ($this->files->isDirectory($path)) {
                return ToolResult::error(\sprintf("'%s' is a directory, not a file", $request->path));
            }

            // Read file content
            $content = $this->files->read($path);

            // Count occurrences of search pattern
            $occurrences = \substr_count($content, $request->search);

            if ($occurrences === 0) {
                $this->logger->warning('Search pattern not found in file', [
                    'path' => $request->path,
                    'searchLength' => \strlen($request->search),
                    'searchPreview' => \substr($request->search, 0, 100),
                ]);

                return ToolResult::error(
                    "Search pattern not found in file. " .
                    "Verify exact match including whitespace, tabs, and line endings. " .
                    "Use file-read tool to see actual file content.",
                );
            }

            if ($occurrences > 1) {
                $this->logger->warning('Search pattern found multiple times', [
                    'path' => $request->path,
                    'occurrences' => $occurrences,
                ]);

                return ToolResult::error(\sprintf(
                    "Search pattern found %d times in file. Pattern must be unique. " .
                    "Add more surrounding context to make it unique.",
                    $occurrences,
                ));
            }

            // Find position for line number calculation
            $position = \strpos($content, $request->search);
            $beforeContent = \substr($content, 0, $position);
            $lineNumber = \substr_count($beforeContent, "\n") + 1;

            // Calculate end line
            $searchLineCount = \substr_count($request->search, "\n");
            $endLineNumber = $lineNumber + $searchLineCount;

            // Perform replacement
            $newContent = \str_replace($request->search, $request->replace, $content);

            // Calculate new end line
            $replaceLineCount = \substr_count($request->replace, "\n");
            $newEndLineNumber = $lineNumber + $replaceLineCount;

            // Write back to file
            $this->files->write($path, $newContent);

            $this->logger->info('Successfully replaced content in file', [
                'path' => $request->path,
                'lineStart' => $lineNumber,
                'lineEnd' => $endLineNumber,
                'newLineEnd' => $newEndLineNumber,
                'charactersReplaced' => \strlen($request->search),
                'charactersAdded' => \strlen($request->replace),
            ]);

            $lineInfo = $lineNumber === $endLineNumber
                ? "line {$lineNumber}"
                : "lines {$lineNumber}-{$endLineNumber}";

            $newLineInfo = $lineNumber === $newEndLineNumber
                ? "line {$lineNumber}"
                : "lines {$lineNumber}-{$newEndLineNumber}";

            return ToolResult::text(\sprintf(
                "Successfully replaced content in file '%s'.\n" .
                "Original: %s (%d characters)\n" .
                "Modified: %s (%d characters)\n" .
                "Change: %+d characters",
                $request->path,
                $lineInfo,
                \strlen($request->search),
                $newLineInfo,
                \strlen($request->replace),
                \strlen($request->replace) - \strlen($request->search),
            ));
        } catch (\Throwable $e) {
            $this->logger->error('Error replacing content in file', [
                'path' => $request->path,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ToolResult::error(\sprintf(
                "Failed to replace content: %s",
                $e->getMessage(),
            ));
        }
    }
}
