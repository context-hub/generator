<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileReplaceContent;

/**
 * Result DTO for file content replacement operations.
 */
final readonly class FileReplaceResult
{
    public function __construct(
        public bool $success,
        public ?string $message = null,
        public ?string $error = null,
        public ?int $lineStart = null,
        public ?int $lineEnd = null,
        public ?int $newLineEnd = null,
        public ?int $charactersReplaced = null,
        public ?int $charactersAdded = null,
    ) {}

    /**
     * Create a successful result with replacement details.
     */
    public static function success(
        string $message,
        int $lineStart,
        int $lineEnd,
        int $newLineEnd,
        int $charactersReplaced,
        int $charactersAdded,
    ): self {
        return new self(
            success: true,
            message: $message,
            lineStart: $lineStart,
            lineEnd: $lineEnd,
            newLineEnd: $newLineEnd,
            charactersReplaced: $charactersReplaced,
            charactersAdded: $charactersAdded,
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
