<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileDeleteContent;

/**
 * Result DTO for file content deletion operations.
 */
final readonly class FileDeleteResult
{
    /**
     * @param array<array{line: int, content: string}> $deletedContent
     */
    public function __construct(
        public bool $success,
        public ?string $message = null,
        public ?string $error = null,
        public int $deletedLines = 0,
        public array $deletedContent = [],
    ) {}

    /**
     * Create a successful result with deletion details.
     *
     * @param array<array{line: int, content: string}> $deletedContent
     */
    public static function success(
        string $message,
        int $deletedLines,
        array $deletedContent,
    ): self {
        return new self(
            success: true,
            message: $message,
            deletedLines: $deletedLines,
            deletedContent: $deletedContent,
        );
    }

    /**
     * Create an error result with a message.
     */
    public static function error(string $message): self
    {
        return new self(
            success: false,
            error: $message,
        );
    }
}
