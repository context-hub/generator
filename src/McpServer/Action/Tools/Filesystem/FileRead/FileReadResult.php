<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileRead;

/**
 * Result DTO representing the outcome of reading a single file.
 */
final readonly class FileReadResult
{
    public function __construct(
        public string $path,
        public bool $success,
        public ?string $content = null,
        public ?string $error = null,
        public ?int $size = null,
        public ?int $totalLines = null,
        public ?int $startLine = null,
        public ?int $endLine = null,
    ) {}

    /**
     * Create a successful read result.
     */
    public static function success(string $path, string $content): self
    {
        return new self(
            path: $path,
            success: true,
            content: $content,
            size: \strlen($content),
        );
    }

    /**
     * Create a successful read result with line range info.
     */
    public static function successWithLineRange(
        string $path,
        string $content,
        int $totalLines,
        int $startLine,
        int $endLine,
    ): self {
        return new self(
            path: $path,
            success: true,
            content: $content,
            size: \strlen($content),
            totalLines: $totalLines,
            startLine: $startLine,
            endLine: $endLine,
        );
    }

    /**
     * Create an error result.
     */
    public static function error(string $path, string $error): self
    {
        return new self(
            path: $path,
            success: false,
            error: $error,
        );
    }

    /**
     * Check if this result contains a partial file (line range).
     */
    public function isPartial(): bool
    {
        return $this->startLine !== null && $this->endLine !== null;
    }
}
