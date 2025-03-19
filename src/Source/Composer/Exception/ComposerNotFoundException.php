<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Composer\Exception;

/**
 * Exception thrown when composer.json cannot be found or read
 */
final class ComposerNotFoundException extends \RuntimeException
{
    public static function fileNotFound(string $path): self
    {
        return new self(\sprintf('composer.json file not found in path: %s', $path));
    }

    public static function cannotParse(string $path, string $reason): self
    {
        return new self(\sprintf('Failed to parse composer.json in %s: %s', $path, $reason));
    }
}
