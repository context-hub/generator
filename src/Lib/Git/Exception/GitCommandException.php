<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Git\Exception;

/**
 * Exception thrown when a git command fails.
 */
final class GitCommandException extends \RuntimeException
{
    /**
     * @param string $message The error message
     * @param int $code The exit code from the git command
     * @param \Throwable|null $previous The previous exception, if any
     */
    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
