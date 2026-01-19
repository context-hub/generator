<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileRead;

use Butschster\ContextGenerator\Config\Exclude\ExcludeRegistryInterface;
use Butschster\ContextGenerator\DirectoriesInterface;
use Psr\Log\LoggerInterface;
use Spiral\Core\Attribute\Proxy;
use Spiral\Files\Exception\FilesException;
use Spiral\Files\FilesInterface;

/**
 * Handler for reading a single file.
 */
final readonly class FileReadHandler
{
    private const int MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 MB

    public function __construct(
        private FilesInterface $files,
        #[Proxy] private DirectoriesInterface $dirs,
        private ExcludeRegistryInterface $excludeRegistry,
        private LoggerInterface $logger,
    ) {}

    /**
     * Read a single file and return the result.
     *
     * @param string $relativePath Path relative to project root
     * @param string $encoding File encoding (currently unused, reserved for future)
     * @param int|null $startLine First line to read (1-based, inclusive)
     * @param int|null $endLine Last line to read (1-based, inclusive)
     */
    public function read(
        string $relativePath,
        string $encoding = 'utf-8',
        ?int $startLine = null,
        ?int $endLine = null,
    ): FileReadResult {
        $fullPath = (string) $this->dirs->getRootPath()->join($relativePath);

        // Check if path is excluded
        if ($this->excludeRegistry->shouldExclude($relativePath)) {
            return FileReadResult::error(
                $relativePath,
                \sprintf("Path '%s' is excluded by project configuration", $relativePath),
            );
        }

        // Validate file exists
        if (!$this->files->exists($fullPath)) {
            $this->logger->warning('File not found', ['path' => $relativePath]);

            return FileReadResult::error(
                $relativePath,
                \sprintf("File '%s' does not exist", $relativePath),
            );
        }

        // Validate not a directory
        if (\is_dir($fullPath)) {
            $this->logger->warning('Path is a directory', ['path' => $relativePath]);

            return FileReadResult::error(
                $relativePath,
                \sprintf("'%s' is a directory, not a file", $relativePath),
            );
        }

        // Check file size
        $size = $this->files->size($fullPath);
        if ($size > self::MAX_FILE_SIZE) {
            $this->logger->warning('File too large', [
                'path' => $relativePath,
                'size' => $size,
                'maxSize' => self::MAX_FILE_SIZE,
            ]);

            return FileReadResult::error(
                $relativePath,
                \sprintf(
                    "File '%s' is too large (%d bytes). Maximum size is %d bytes.",
                    $relativePath,
                    $size,
                    self::MAX_FILE_SIZE,
                ),
            );
        }

        try {
            $content = $this->files->read($fullPath);

            // Handle line range if specified
            if ($startLine !== null || $endLine !== null) {
                return $this->extractLineRange($relativePath, $content, $startLine, $endLine);
            }

            $this->logger->info('Successfully read file', [
                'path' => $relativePath,
                'size' => \strlen($content),
            ]);

            return FileReadResult::success($relativePath, $content);
        } catch (FilesException $e) {
            $this->logger->error('Failed to read file', [
                'path' => $relativePath,
                'error' => $e->getMessage(),
            ]);

            return FileReadResult::error(
                $relativePath,
                \sprintf("Could not read file '%s': %s", $relativePath, $e->getMessage()),
            );
        }
    }

    /**
     * Extract a range of lines from content.
     */
    private function extractLineRange(
        string $relativePath,
        string $content,
        ?int $startLine,
        ?int $endLine,
    ): FileReadResult {
        $lines = \explode("\n", $content);
        $totalLines = \count($lines);

        // Normalize line numbers (1-based to 0-based index)
        $effectiveStart = \max(1, $startLine ?? 1);
        $effectiveEnd = $endLine !== null ? \min($endLine, $totalLines) : $totalLines;

        // Validate range
        if ($effectiveStart > $totalLines) {
            return FileReadResult::error(
                $relativePath,
                \sprintf(
                    "Start line %d exceeds file length (%d lines)",
                    $effectiveStart,
                    $totalLines,
                ),
            );
        }

        if ($effectiveStart > $effectiveEnd) {
            return FileReadResult::error(
                $relativePath,
                \sprintf(
                    "Invalid line range: start (%d) is greater than end (%d)",
                    $effectiveStart,
                    $effectiveEnd,
                ),
            );
        }

        // Extract lines (convert to 0-based index)
        $selectedLines = \array_slice($lines, $effectiveStart - 1, $effectiveEnd - $effectiveStart + 1);
        $extractedContent = \implode("\n", $selectedLines);

        $this->logger->info('Successfully read file with line range', [
            'path' => $relativePath,
            'totalLines' => $totalLines,
            'startLine' => $effectiveStart,
            'endLine' => $effectiveEnd,
            'extractedLines' => \count($selectedLines),
        ]);

        return FileReadResult::successWithLineRange(
            $relativePath,
            $extractedContent,
            $totalLines,
            $effectiveStart,
            $effectiveEnd,
        );
    }
}
