<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Logger;

use Psr\Log\AbstractLogger;

/**
 * A logger implementation that discards all log messages.
 * Used when logging is disabled or in testing contexts.
 */
final class NullLogger extends AbstractLogger implements HasPrefixLoggerInterface
{
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        // Intentionally do nothing
    }

    public function withPrefix(string $prefix): static
    {
        return $this;
    }

    public function getPrefix(): string
    {
        return '';
    }
}
