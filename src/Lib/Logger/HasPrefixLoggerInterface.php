<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Logger;

/**
 * Interface for loggers that support message prefixing.
 */
interface HasPrefixLoggerInterface
{
    /**
     * Creates a new logger instance with the specified prefix.
     *
     * @param string $prefix The prefix to prepend to log messages
     */
    public function withPrefix(string $prefix): static;

    /**
     * Returns the current prefix used by this logger.
     */
    public function getPrefix(): string;
}
