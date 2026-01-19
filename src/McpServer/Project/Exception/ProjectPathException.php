<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Project\Exception;

/**
 * Exception thrown when project path resolution fails.
 */
final class ProjectPathException extends \RuntimeException
{
    public function __construct(
        public readonly string $path,
        public readonly string $reason,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function notFound(string $path): self
    {
        return new self(
            path: $path,
            reason: 'not_found',
            message: \sprintf("Project path '%s' does not exist.", $path),
        );
    }

    public static function notDirectory(string $path): self
    {
        return new self(
            path: $path,
            reason: 'not_directory',
            message: \sprintf("Project path '%s' is not a directory.", $path),
        );
    }

    public static function notReadable(string $path): self
    {
        return new self(
            path: $path,
            reason: 'not_readable',
            message: \sprintf("Project path '%s' is not readable.", $path),
        );
    }
}
