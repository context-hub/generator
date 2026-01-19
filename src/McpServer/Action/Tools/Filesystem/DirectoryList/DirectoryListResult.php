<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\DirectoryList;

/**
 * Result DTO for directory listing operations.
 */
final readonly class DirectoryListResult
{
    /**
     * @param bool $success Whether the operation completed successfully
     * @param int $count Number of items returned
     * @param int $totalMatched Total number of items found before truncation
     * @param bool $truncated Whether results were truncated due to maxResults limit
     * @param string $basePath Base path that was searched
     * @param array<array{name: string, path: string, fullPath: string, isDirectory: bool, size: int|null, lastModified: string}>|null $files List of files/directories found
     * @param string|null $treeView Tree view representation (if requested)
     * @param string|null $warning Warning message (e.g., when results are truncated)
     * @param string|null $error Error message if operation failed
     */
    public function __construct(
        public bool $success,
        public int $count = 0,
        public int $totalMatched = 0,
        public bool $truncated = false,
        public string $basePath = '',
        public ?array $files = null,
        public ?string $treeView = null,
        public ?string $warning = null,
        public ?string $error = null,
    ) {}

    /**
     * @param array<array{name: string, path: string, fullPath: string, isDirectory: bool, size: int|null, lastModified: string}> $files
     */
    public static function success(
        string $basePath,
        array $files,
        int $totalMatched = 0,
        bool $truncated = false,
        ?string $warning = null,
    ): self {
        return new self(
            success: true,
            count: \count($files),
            totalMatched: $totalMatched ?: \count($files),
            truncated: $truncated,
            basePath: $basePath,
            files: $files,
            warning: $warning,
        );
    }

    /**
     * @param array<array{name: string, path: string, fullPath: string, isDirectory: bool, size: int|null, lastModified: string}> $files
     */
    public static function successWithTree(
        string $basePath,
        array $files,
        string $treeView,
        int $totalMatched = 0,
        bool $truncated = false,
        ?string $warning = null,
    ): self {
        return new self(
            success: true,
            count: \count($files),
            totalMatched: $totalMatched ?: \count($files),
            truncated: $truncated,
            basePath: $basePath,
            files: $files,
            treeView: $treeView,
            warning: $warning,
        );
    }

    public static function error(string $error): self
    {
        return new self(
            success: false,
            error: $error,
        );
    }

    /**
     * Convert to response data array.
     *
     * @return array<string, mixed>
     */
    public function toResponseData(): array
    {
        $data = [
            'count' => $this->count,
            'totalMatched' => $this->totalMatched,
            'basePath' => $this->basePath,
        ];

        if ($this->truncated) {
            $data['truncated'] = true;
        }

        if ($this->warning !== null) {
            $data['warning'] = $this->warning;
        }

        if ($this->treeView !== null) {
            $data['treeView'] = $this->treeView;
        } else {
            $data['files'] = $this->files;
        }

        return $data;
    }
}
