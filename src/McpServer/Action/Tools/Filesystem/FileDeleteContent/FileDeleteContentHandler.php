<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileDeleteContent;

use Butschster\ContextGenerator\Config\Exclude\ExcludeRegistryInterface;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileDeleteContent\Dto\FileDeleteContentRequest;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileReplaceContent\LineEnding;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileReplaceContent\LineEndingNormalizer;
use Psr\Log\LoggerInterface;
use Spiral\Core\Attribute\Proxy;
use Spiral\Files\FilesInterface;

/**
 * Core handler for file content deletion operations.
 *
 * Handles line-based deletions with descending order processing to avoid offset issues.
 */
final readonly class FileDeleteContentHandler
{
    public function __construct(
        private FilesInterface $files,
        #[Proxy] private DirectoriesInterface $dirs,
        private LineEndingNormalizer $normalizer,
        private ExcludeRegistryInterface $excludeRegistry,
        private LoggerInterface $logger,
    ) {}

    /**
     * Execute the file content deletion operation.
     */
    public function handle(FileDeleteContentRequest $request): FileDeleteResult
    {
        $path = (string) $this->dirs->getRootPath()->join($request->path);

        // Check if path is excluded
        if ($this->excludeRegistry->shouldExclude($request->path)) {
            return FileDeleteResult::error(
                \sprintf("Path '%s' is excluded by project configuration", $request->path),
            );
        }

        // Validate file exists
        if (!$this->files->exists($path)) {
            return FileDeleteResult::error(
                \sprintf("File '%s' does not exist", $request->path),
            );
        }

        // Validate not a directory
        if ($this->files->isDirectory($path)) {
            return FileDeleteResult::error(
                \sprintf("'%s' is a directory, not a file", $request->path),
            );
        }

        // Get line numbers
        $lineNumbers = $request->getLineNumbers();

        if ($lineNumbers === []) {
            return FileDeleteResult::error(
                'No valid line numbers provided. Specify line numbers as integers or ranges {"from": N, "to": M}.',
            );
        }

        // Read file content
        $content = $this->files->read($path);

        // Detect the file's line ending style
        $fileLineEnding = $this->normalizer->detect($content);

        // Split content into lines
        $lines = $this->splitIntoLines($content, $fileLineEnding);
        $totalLines = \count($lines);

        // Validate line numbers are within bounds
        foreach ($lineNumbers as $lineNum) {
            if ($lineNum < 1 || $lineNum > $totalLines) {
                return FileDeleteResult::error(\sprintf(
                    "Invalid line number %d. File has %d lines. Valid range: 1-%d.",
                    $lineNum,
                    $totalLines,
                    $totalLines,
                ));
            }
        }

        // Sort in descending order to delete from bottom to top
        // This way, deleting a line doesn't affect the indices of lines above it
        $sortedLines = $lineNumbers;
        \rsort($sortedLines);

        // Delete lines and collect deleted content
        $deletedContent = [];
        foreach ($sortedLines as $lineNum) {
            $index = $lineNum - 1; // Convert to 0-based index
            $deletedContent[] = [
                'line' => $lineNum,
                'content' => $lines[$index],
            ];
            \array_splice($lines, $index, 1);
        }

        // Reverse to return in original (ascending) order
        $deletedContent = \array_reverse($deletedContent);

        // Rebuild file content
        $newContent = \implode($fileLineEnding->value, $lines);

        // Write back to file
        $this->files->write($path, $newContent);

        $deletedCount = \count($lineNumbers);

        $this->logger->info('Successfully deleted content from file', [
            'path' => $request->path,
            'deletedLines' => $deletedCount,
            'lineNumbers' => $lineNumbers,
            'fileLineEnding' => $fileLineEnding->name,
        ]);

        // Build success message
        $lineWord = $deletedCount === 1 ? 'line' : 'lines';
        $message = \sprintf(
            "Successfully deleted %d %s from file '%s'.",
            $deletedCount,
            $lineWord,
            $request->path,
        );

        return FileDeleteResult::success(
            message: $message,
            deletedLines: $deletedCount,
            deletedContent: $deletedContent,
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
}
