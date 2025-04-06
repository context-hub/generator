<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool\Exception;

/**
 * Exception thrown when a tool execution fails.
 */
final class ToolExecutionException extends \RuntimeException
{
    /**
     * Creates a new tool execution exception with command details.
     */
    public static function fromCommand(
        string $command,
        array $args = [],
        string $reason = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ): self {
        $commandStr = $command . ' ' . \implode(' ', $args);
        $message = \sprintf('Failed to execute command: %s', $commandStr);

        if ($reason !== '') {
            $message .= \sprintf('. Reason: %s', $reason);
        }

        return new self($message, $code, $previous);
    }
}
