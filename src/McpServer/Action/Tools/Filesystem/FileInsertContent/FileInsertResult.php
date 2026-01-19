<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileInsertContent;

/**
 * Result DTO for file content insertion operations.
 */
final readonly class FileInsertResult
{
    /**
     * @param array<array{line: int, linesInserted: int}> $insertions
     */
    public function __construct(
        public bool $success,
        public ?string $message = null,
        public ?string $error = null,
        public int $totalLinesInserted = 0,
        public int $totalInsertions = 0,
        public array $insertions = [],
    ) {}

    /**
     * Create a successful result with insertion details.
     *
     * @param array<array{line: int, linesInserted: int}> $insertions
     */
    public static function success(
        string $message,
        int $totalLinesInserted,
        int $totalInsertions,
        array $insertions,
    ): self {
        return new self(
            success: true,
            message: $message,
            totalLinesInserted: $totalLinesInserted,
            totalInsertions: $totalInsertions,
            insertions: $insertions,
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
