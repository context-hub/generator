<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

/**
 * A logger implementation that discards all log messages.
 * Used when logging is disabled or in testing contexts.
 */
final class NullLogger extends AbstractLogger implements LoggerInterface, HasPrefixLoggerInterface
{
    /**
     * Discards the log message (no-op implementation).
     *
     * @param mixed $level
     * @param array<string, mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        // Intentionally do nothing
    }

    public function withPrefix(string $prefix): HasPrefixLoggerInterface
    {
        return $this;
    }

    public function getPrefix(): string
    {
        return '';
    }
}
