<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\ConfigLoader\Import\PathPrefixer;

/**
 * Abstract base class for path prefix operations
 */
abstract readonly class PathPrefixer
{
    /**
     * Apply a path prefix to the appropriate paths in a configuration
     */
    abstract public function applyPrefix(array $config, string $pathPrefix): array;

    /**
     * Combine two paths with a separator
     */
    protected function combinePaths(string $prefix, string $path): string
    {
        return \rtrim($prefix, '/') . '/' . \ltrim($path, '/');
    }

    /**
     * Check if path is absolute
     */
    protected function isAbsolutePath(string $path): bool
    {
        return \str_starts_with($path, '/');
    }
}
