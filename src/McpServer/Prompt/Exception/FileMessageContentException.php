<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt\Exception;

/**
 * Exception thrown when file or URL content loading fails.
 */
final class FileMessageContentException extends PromptParsingException
{
    public static function fileNotFound(string $filePath): self
    {
        return new self(\sprintf('File not found: %s', $filePath));
    }

    public static function fileNotReadable(string $filePath): self
    {
        return new self(\sprintf('File is not readable: %s', $filePath));
    }

    public static function urlLoadFailed(string $url, string $reason): self
    {
        return new self(\sprintf('Failed to load URL "%s": %s', $url, $reason));
    }

    public static function invalidUrl(string $url): self
    {
        return new self(\sprintf('Invalid URL: %s', $url));
    }

    public static function emptyContent(string $source): self
    {
        return new self(\sprintf('Content is empty: %s', $source));
    }
}
