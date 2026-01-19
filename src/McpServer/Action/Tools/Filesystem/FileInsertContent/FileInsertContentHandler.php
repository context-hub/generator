<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileInsertContent;

use Butschster\ContextGenerator\Config\Exclude\ExcludeRegistryInterface;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileInsertContent\Dto\FileInsertContentRequest;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileInsertContent\Dto\InsertionItem;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileReplaceContent\LineEnding;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileReplaceContent\LineEndingNormalizer;
use Psr\Log\LoggerInterface;
use Spiral\Core\Attribute\Proxy;
use Spiral\Files\FilesInterface;

/**
 * Core handler for file content insertion operations.
 *
 * Handles line-based insertions with automatic offset calculation for multiple insertions.
 */
final readonly class FileInsertContentHandler
{
    public function __construct(
        private FilesInterface $files,
        #[Proxy] private DirectoriesInterface $dirs,
        private LineEndingNormalizer $normalizer,
        private ExcludeRegistryInterface $excludeRegistry,
        private LoggerInterface $logger,
    ) {}

    /**
     * Execute the file content insertion operation.
     */
    public function handle(FileInsertContentRequest $request): FileInsertResult
    {
        $path = (string) $this->dirs->getRootPath()->join($request->path);

        // Check if path is excluded
        if ($this->excludeRegistry->shouldExclude($request->path)) {
            return FileInsertResult::error(
                \sprintf("Path '%s' is excluded by project configuration", $request->path),
            );
        }

        // Validate file exists
        if (!$this->files->exists($path)) {
            return FileInsertResult::error(
                \sprintf("File '%s' does not exist", $request->path),
            );
        }

        // Validate not a directory
        if ($this->files->isDirectory($path)) {
            return FileInsertResult::error(
                \sprintf("'%s' is a directory, not a file", $request->path),
            );
        }

        // Validate position parameter
        if (!\in_array($request->position, ['before', 'after'], true)) {
            return FileInsertResult::error(
                "Invalid position parameter. Must be 'before' or 'after'.",
            );
        }

        // Get insertion items
        $insertionItems = $request->getInsertionItems();

        if ($insertionItems === []) {
            return FileInsertResult::error(
                'No valid insertions provided. Each insertion must have "line" and "content" fields.',
            );
        }

        // Read file content
        $content = $this->files->read($path);

        // Detect the file's line ending style
        $fileLineEnding = $this->normalizer->detect($content);
        $lineEndingStr = $fileLineEnding->value;

        // Split content into lines
        $lines = $this->splitIntoLines($content, $fileLineEnding);
        $totalLines = \count($lines);

        // Validate line numbers
        foreach ($insertionItems as $item) {
            if ($item->line !== -1 && ($item->line < 1 || $item->line > $totalLines)) {
                return FileInsertResult::error(\sprintf(
                    "Invalid line number %d. File has %d lines. Use 1-%d or -1 for end of file.",
                    $item->line,
                    $totalLines,
                    $totalLines,
                ));
            }
        }

        // Sort insertions by line number (ascending) for proper offset calculation
        $sortedItems = $this->sortInsertions($insertionItems, $totalLines);

        // Process insertions with offset tracking
        $offset = 0;
        $insertionResults = [];
        $totalLinesInserted = 0;

        foreach ($sortedItems as $item) {
            // Resolve line number (-1 means end of file)
            $targetLine = $item->line === -1 ? $totalLines : $item->line;

            // Apply offset from previous insertions
            $actualLine = $targetLine + $offset;

            // Normalize content line endings to match file
            $normalizedContent = $this->normalizer->normalize($item->content, $fileLineEnding);

            // Split inserted content into lines
            $newLines = \explode($lineEndingStr, $normalizedContent);
            $newLinesCount = \count($newLines);

            // Calculate insert index (0-based)
            $insertIndex = $request->position === 'after'
                ? $actualLine
                : $actualLine - 1;

            // Ensure index is within bounds
            $insertIndex = \max(0, \min($insertIndex, \count($lines)));

            // Insert lines
            \array_splice($lines, $insertIndex, 0, $newLines);

            // Track results
            $insertionResults[] = [
                'line' => $item->line,
                'linesInserted' => $newLinesCount,
            ];

            // Update offset for next insertion
            $offset += $newLinesCount;
            $totalLinesInserted += $newLinesCount;
        }

        // Rebuild file content
        $newContent = \implode($lineEndingStr, $lines);

        // Write back to file
        $this->files->write($path, $newContent);

        $this->logger->info('Successfully inserted content in file', [
            'path' => $request->path,
            'position' => $request->position,
            'totalInsertions' => \count($insertionItems),
            'totalLinesInserted' => $totalLinesInserted,
            'fileLineEnding' => $fileLineEnding->name,
        ]);

        // Build success message
        $locationWord = \count($insertionItems) === 1 ? 'location' : 'locations';
        $message = \sprintf(
            "Successfully inserted %d line(s) across %d %s in file '%s'.",
            $totalLinesInserted,
            \count($insertionItems),
            $locationWord,
            $request->path,
        );

        return FileInsertResult::success(
            message: $message,
            totalLinesInserted: $totalLinesInserted,
            totalInsertions: \count($insertionItems),
            insertions: $insertionResults,
        );
    }

    /**
     * Split content into lines, preserving empty lines.
     *
     * @return string[]
     */
    private function splitIntoLines(string $content, LineEnding $lineEnding): array
    {
        if ($content === '') {
            return [''];
        }

        return \explode($lineEnding->value, $content);
    }

    /**
     * Sort insertions by line number ascending, resolving -1 to actual line number.
     *
     * @param InsertionItem[] $items
     * @return InsertionItem[]
     */
    private function sortInsertions(array $items, int $totalLines): array
    {
        \usort($items, static function (InsertionItem $a, InsertionItem $b) use ($totalLines): int {
            $lineA = $a->line === -1 ? $totalLines + 1 : $a->line;
            $lineB = $b->line === -1 ? $totalLines + 1 : $b->line;

            return $lineA <=> $lineB;
        });

        return $items;
    }
}
