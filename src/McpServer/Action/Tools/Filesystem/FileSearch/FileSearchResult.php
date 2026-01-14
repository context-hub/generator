<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileSearch;

/**
 * Result DTO containing all matches found in a single file.
 */
final readonly class FileSearchResult
{
    /**
     * @param string $file Relative file path
     * @param bool $success Whether search completed without errors
     * @param SearchMatch[] $matches Array of matches found
     * @param string|null $error Error message if search failed
     * @param bool $truncated Whether results were truncated due to limits
     * @param int $fileSize File size in bytes
     * @param int|null $lastModified Last modification timestamp (unix)
     */
    public function __construct(
        public string $file,
        public bool $success,
        public array $matches = [],
        public ?string $error = null,
        public bool $truncated = false,
        public int $fileSize = 0,
        public ?int $lastModified = null,
    ) {}

    public static function success(
        string $file,
        array $matches,
        bool $truncated = false,
        int $fileSize = 0,
        ?int $lastModified = null,
    ): self {
        return new self(
            file: $file,
            success: true,
            matches: $matches,
            truncated: $truncated,
            fileSize: $fileSize,
            lastModified: $lastModified,
        );
    }

    public static function error(string $file, string $error): self
    {
        return new self(
            file: $file,
            success: false,
            error: $error,
        );
    }

    public function getMatchCount(): int
    {
        return \count($this->matches);
    }

    /**
     * Format all matches in this file for text output.
     */
    public function format(): string
    {
        if (!$this->success) {
            return \sprintf("=== %s ===\nError: %s", $this->file, $this->error);
        }

        if (empty($this->matches)) {
            return '';
        }

        $maxLineNum = \max(\array_map(fn(SearchMatch $m) => $m->lineNumber, $this->matches));
        $lineNumWidth = \strlen((string) $maxLineNum);

        $header = \sprintf('=== %s %s===', $this->file, $this->formatMetadata());
        $parts = [$header];

        foreach ($this->matches as $match) {
            $parts[] = '';
            $parts[] = \sprintf('[Line %d]', $match->lineNumber);
            $parts[] = $match->format($lineNumWidth);
        }

        if ($this->truncated) {
            $parts[] = '';
            $parts[] = '... (results truncated)';
        }

        return \implode("\n", $parts);
    }

    /**
     * Format file metadata (size and last modified) for display.
     */
    private function formatMetadata(): string
    {
        $parts = [];

        if ($this->fileSize > 0) {
            $parts[] = $this->formatFileSize($this->fileSize);
        }

        if ($this->lastModified !== null) {
            $parts[] = 'modified ' . $this->formatRelativeTime($this->lastModified);
        }

        return $parts !== [] ? '[' . \implode(', ', $parts) . '] ' : '';
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return \sprintf('%.1f MB', $bytes / (1024 * 1024));
        }
        if ($bytes >= 1024) {
            return \sprintf('%.1f KB', $bytes / 1024);
        }
        return \sprintf('%d B', $bytes);
    }

    private function formatRelativeTime(int $timestamp): string
    {
        $diff = \time() - $timestamp;

        if ($diff < 60) {
            return 'just now';
        }
        if ($diff < 3600) {
            $mins = (int) ($diff / 60);
            return $mins . 'm ago';
        }
        if ($diff < 86400) {
            $hours = (int) ($diff / 3600);
            return $hours . 'h ago';
        }
        if ($diff < 86400 * 30) {
            $days = (int) ($diff / 86400);
            return $days . 'd ago';
        }

        return \date('Y-m-d', $timestamp);
    }
}
