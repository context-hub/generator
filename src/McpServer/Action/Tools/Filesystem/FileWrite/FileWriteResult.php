<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileWrite;

/**
 * Result DTO for file write operations.
 */
final readonly class FileWriteResult
{
    public function __construct(
        public bool $success,
        public ?string $message = null,
        public ?string $error = null,
        public ?int $bytesWritten = null,
        public ?string $path = null,
    ) {}

    /**
     * Create a successful result with details.
     */
    public static function success(string $path, int $bytesWritten): self
    {
        return new self(
            success: true,
            message: \sprintf("Successfully wrote %d bytes to file '%s'", $bytesWritten, $path),
            bytesWritten: $bytesWritten,
            path: $path,
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
