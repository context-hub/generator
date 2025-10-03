<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Logger;

use Psr\Log\LogLevel as PsrLogLevel;

/**
 * Enum representing log levels with their associated ANSI colors for console output.
 */
enum LogLevel: string
{
    case EMERGENCY = PsrLogLevel::EMERGENCY;
    case ALERT = PsrLogLevel::ALERT;
    case CRITICAL = PsrLogLevel::CRITICAL;
    case ERROR = PsrLogLevel::ERROR;
    case WARNING = PsrLogLevel::WARNING;
    case NOTICE = PsrLogLevel::NOTICE;
    case INFO = PsrLogLevel::INFO;
    case DEBUG = PsrLogLevel::DEBUG;

    /**
     * Create a LogLevel enum from a string.
     */
    public static function fromString(string $level): self
    {
        $normalizedLevel = \strtolower(string: $level);

        return match ($normalizedLevel) {
            PsrLogLevel::EMERGENCY => self::EMERGENCY,
            PsrLogLevel::ALERT => self::ALERT,
            PsrLogLevel::CRITICAL => self::CRITICAL,
            PsrLogLevel::ERROR => self::ERROR,
            PsrLogLevel::WARNING => self::WARNING,
            PsrLogLevel::NOTICE => self::NOTICE,
            PsrLogLevel::DEBUG => self::DEBUG,
            default => self::INFO, // Default to INFO for unrecognized levels
        };
    }

    /**
     * Get the ANSI color code for the log level.
     */
    public function getAnsiColor(): string
    {
        return match ($this) {
            self::EMERGENCY, self::ALERT, self::CRITICAL => "\033[1;31m", // Bold Red
            self::ERROR => "\033[0;31m",                                  // Red
            self::WARNING => "\033[0;33m",                                // Yellow
            self::NOTICE => "\033[0;32m",                                 // Green
            self::INFO => "\033[0;36m",                                   // Cyan
            self::DEBUG => "\033[0;90m",                                  // Dark Gray
        };
    }

    /**
     * Get the log level label in uppercase.
     */
    public function getLabel(): string
    {
        return \strtoupper(string: $this->value);
    }
}
