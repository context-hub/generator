<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Logger;

/**
 * Interface for log message formatters.
 */
interface FormatterInterface extends HasPrefixLoggerInterface
{
    /**
     * Format a log message with the given level and context.
     *
     * @param string $level PSR-3 log level
     * @param string|\Stringable $message The message to format
     * @param array<string, mixed> $context Additional context data
     *
     * @return string The formatted message
     */
    public function format(string $level, string|\Stringable $message, array $context = []): string;
}
