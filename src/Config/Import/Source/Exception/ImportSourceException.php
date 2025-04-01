<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import\Source\Exception;

/**
 * Exception thrown when an import source encounters an error
 */
class ImportSourceException extends \RuntimeException
{
    /**
     * Create an exception for when a source is not supported
     */
    public static function sourceNotSupported(string $path, string $type): self
    {
        return new self(\sprintf('Import source not supported for path "%s" with type "%s"', $path, $type));
    }

    /**
     * Create an exception for when a network error occurs
     */
    public static function networkError(string $url, string $message): self
    {
        return new self(\sprintf('Failed to fetch from URL "%s": %s', $url, $message));
    }
}
