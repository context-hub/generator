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
     * Create an exception for when a file is not found
     */
    public static function fileNotFound(string $path): self
    {
        return new self(\sprintf('Import file not found: %s', $path));
    }

    /**
     * Create an exception for when a network error occurs
     */
    public static function networkError(string $url, string $message): self
    {
        return new self(\sprintf('Failed to fetch from URL "%s": %s', $url, $message));
    }

    /**
     * Create an exception for when GitHub API access fails
     */
    public static function githubError(string $repository, string $message): self
    {
        return new self(
            \sprintf('Failed to fetch from GitHub repository "%s": %s', $repository, $message),
        );
    }

    /**
     * Create an exception for when a composer package is not found
     */
    public static function composerPackageNotFound(string $path): self
    {
        return new self(\sprintf('Composer package not found at path: %s', $path));
    }
}
