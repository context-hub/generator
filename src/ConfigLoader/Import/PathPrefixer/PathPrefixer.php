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
        $combined = \rtrim($prefix, '/') . '/' . \ltrim($path, '/');
        return $this->normalizePath($combined);
    }

    /**
     * Check if path is absolute
     */
    protected function isAbsolutePath(string $path): bool
    {
        return \str_starts_with($path, '/');
    }

    /**
     * Normalize a path by removing redundant directory references
     */
    protected function normalizePath(string $path): string
    {
        // Don't process empty paths
        if (empty($path)) {
            return $path;
        }

        // Preserve leading slash for absolute paths
        $isAbsolute = $this->isAbsolutePath($path);

        // Replace backslashes with forward slashes for consistency
        $path = \str_replace('\\', '/', $path);

        // Split the path into segments
        $segments = \explode('/', $path);

        // Process each segment
        $result = [];
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                // Skip empty segments and current directory references
                continue;
            }

            if ($segment === '..') {
                // Handle parent directory references by removing the last segment
                if (!empty($result) && $result[\count($result) - 1] !== '..') {
                    \array_pop($result);
                } else {
                    // If no segments to pop, preserve the .. reference
                    $result[] = $segment;
                }
            } else {
                // Add normal segments
                $result[] = $segment;
            }
        }

        // Combine the segments
        $normalized = \implode('/', $result);

        // Restore leading slash for absolute paths
        if ($isAbsolute && !empty($normalized)) {
            $normalized = '/' . $normalized;
        } elseif (empty($normalized)) {
            // Handle special case for empty result (e.g., normalizePath("./"))
            $normalized = $isAbsolute ? '/' : '.';
        }

        return $normalized;
    }
}
