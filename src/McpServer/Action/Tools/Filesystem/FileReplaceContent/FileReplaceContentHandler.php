<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileReplaceContent;

use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileReplaceContent\Dto\FileReplaceContentRequest;
use Psr\Log\LoggerInterface;
use Spiral\Core\Attribute\Proxy;
use Spiral\Files\FilesInterface;

/**
 * Core handler for file content replacement operations.
 *
 * Handles line ending normalization to ensure cross-platform compatibility.
 */
final readonly class FileReplaceContentHandler
{
    public function __construct(
        private FilesInterface $files,
        #[Proxy] private DirectoriesInterface $dirs,
        private LineEndingNormalizer $normalizer,
        private LoggerInterface $logger,
    ) {}

    /**
     * Execute the file content replacement operation.
     */
    public function handle(FileReplaceContentRequest $request): FileReplaceResult
    {
        $path = (string) $this->dirs->getRootPath()->join($request->path);

        // Validate file exists
        if (!$this->files->exists($path)) {
            return FileReplaceResult::error(
                \sprintf("File '%s' does not exist", $request->path),
            );
        }

        // Validate not a directory
        if ($this->files->isDirectory($path)) {
            return FileReplaceResult::error(
                \sprintf("'%s' is a directory, not a file", $request->path),
            );
        }

        // Read file content
        $content = $this->files->read($path);

        // Detect the file's line ending style
        $fileLineEnding = $this->normalizer->detect($content);

        // Normalize search and replace patterns to match file's line ending style
        $normalizedSearch = $this->normalizer->normalize($request->search, $fileLineEnding);
        $normalizedReplace = $this->normalizer->normalize($request->replace, $fileLineEnding);

        // Count occurrences of normalized search pattern
        $occurrences = \substr_count($content, $normalizedSearch);

        if ($occurrences === 0) {
            $this->logger->warning('Search pattern not found in file', [
                'path' => $request->path,
                'searchLength' => \strlen($request->search),
                'searchPreview' => \substr($request->search, 0, 100),
                'fileLineEnding' => $fileLineEnding->name,
            ]);

            return FileReplaceResult::error(
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

            return FileReplaceResult::error(\sprintf(
                "Search pattern found %d times in file. Pattern must be unique. " .
                "Add more surrounding context to make it unique.",
                $occurrences,
            ));
        }

        // Find position for line number calculation
        $position = \strpos($content, $normalizedSearch);
        $beforeContent = \substr($content, 0, $position);

        // Use the file's line ending for counting lines
        $lineEndingStr = $fileLineEnding->value;
        $lineNumber = \substr_count($beforeContent, $lineEndingStr) + 1;

        // Calculate end line
        $searchLineCount = \substr_count($normalizedSearch, $lineEndingStr);
        $endLineNumber = $lineNumber + $searchLineCount;

        // Perform replacement
        $newContent = \str_replace($normalizedSearch, $normalizedReplace, $content);

        // Calculate new end line
        $replaceLineCount = \substr_count($normalizedReplace, $lineEndingStr);
        $newEndLineNumber = $lineNumber + $replaceLineCount;

        // Write back to file
        $this->files->write($path, $newContent);

        $charactersReplaced = \strlen($normalizedSearch);
        $charactersAdded = \strlen($normalizedReplace);

        $this->logger->info('Successfully replaced content in file', [
            'path' => $request->path,
            'lineStart' => $lineNumber,
            'lineEnd' => $endLineNumber,
            'newLineEnd' => $newEndLineNumber,
            'charactersReplaced' => $charactersReplaced,
            'charactersAdded' => $charactersAdded,
            'fileLineEnding' => $fileLineEnding->name,
        ]);

        // Build success message
        $lineInfo = $lineNumber === $endLineNumber
            ? "line {$lineNumber}"
            : "lines {$lineNumber}-{$endLineNumber}";

        $newLineInfo = $lineNumber === $newEndLineNumber
            ? "line {$lineNumber}"
            : "lines {$lineNumber}-{$newEndLineNumber}";

        $message = \sprintf(
            "Successfully replaced content in file '%s'.\n" .
            "Original: %s (%d characters)\n" .
            "Modified: %s (%d characters)\n" .
            "Change: %+d characters",
            $request->path,
            $lineInfo,
            $charactersReplaced,
            $newLineInfo,
            $charactersAdded,
            $charactersAdded - $charactersReplaced,
        );

        return FileReplaceResult::success(
            message: $message,
            lineStart: $lineNumber,
            lineEnd: $endLineNumber,
            newLineEnd: $newEndLineNumber,
            charactersReplaced: $charactersReplaced,
            charactersAdded: $charactersAdded,
        );
    }
}
