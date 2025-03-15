<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Logger;

/**
 * Default formatter implementation that formats log messages with timestamp,
 * level indicator, and interpolates context data.
 */
final readonly class SimpleFormatter implements FormatterInterface
{
    /**
     * @param string $dateFormat The date format string for timestamps
     * @param bool $includeTimestamp Whether to include timestamps in log messages
     */
    public function __construct(
        private string $prefix = '',
        private string $dateFormat = 'Y-m-d H:i:s',
        private bool $includeTimestamp = false,
    ) {}

    /**
     * Format a log message with the given level and context.
     *
     * @param string $level PSR-3 log level
     * @param string|\Stringable $message The message to format
     * @param array<string, mixed> $context Additional context data
     *
     * @return string The formatted message
     */
    public function format(string $level, string|\Stringable $message, array $context = []): string
    {
        $logLevel = LogLevel::fromString($level);
        $formattedMessage = (string) $message;

        // Interpolate placeholders in the message
        $formattedMessage = $this->interpolate($formattedMessage, $context);

        // Build the complete formatted line
        $parts = [];

        if ($this->includeTimestamp) {
            $parts[] = '[' . \date($this->dateFormat) . ']';
        }

        $parts[] = '[' . $logLevel->getLabel() . ']';
        if (!empty($this->prefix)) {
            $parts[] = '[' . $this->prefix . ']';
        }

        $parts[] = $formattedMessage;

        return ' ' . \implode(' ', $parts);
    }

    public function withPrefix(string $prefix): self
    {
        return new self(
            $prefix,
            $this->dateFormat,
            $this->includeTimestamp,
        );
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Interpolate context values into message placeholders.
     *
     * Placeholders must be in the format {key}.
     *
     * @param string $message The message with placeholders
     * @param array<string, mixed> $context The context values to interpolate
     *
     * @return string The interpolated message
     */
    private function interpolate(string $message, array $context): string
    {
        if (empty($context)) {
            return $message;
        }

        $json = \json_encode($context, \JSON_PRETTY_PRINT);
        // add indentation fo each line
        $json = \preg_replace('/^/m', ' ', $json);

        return $message . "\n\n" . $json . "\n";
    }

    /**
     * Format exception details for inclusion in log messages.
     */
    private function formatException(\Throwable $exception): string
    {
        return \sprintf(
            "\nException: %s [%d]: %s at %s line %s\nStack trace:\n%s",
            $exception::class,
            $exception->getCode(),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString(),
        );
    }
}
