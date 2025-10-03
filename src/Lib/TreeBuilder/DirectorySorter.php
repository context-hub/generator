<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\TreeBuilder;

/**
 * Utility class for sorting directory paths in a hierarchical order
 * ensuring parent directories appear before their children
 */
final class DirectorySorter
{
    /**
     * Sort directories with parent directories appearing before their children
     *
     * @param array<string> $directories List of directory paths to sort
     * @return array<string> Sorted directory paths
     */
    public static function sort(iterable $directories): array
    {
        // First, remove any duplicates and ensure consistent path separators
        $normalized = \array_map(
            callback: static fn(string $path): string => self::normalizePath($path),
            array: \array_unique(array: (array) $directories),
        );

        // Early return for empty arrays or single item arrays (already sorted)
        if (\count(value: $normalized) <= 1) {
            return $normalized;
        }

        // First sort alphabetically to ensure consistent ordering
        \sort(array: $normalized);

        // Then re-sort to ensure parent directories appear before their children
        \usort(array: $normalized, callback: static function (string $a, string $b): int {
            // If one path is a direct parent of another, make sure the parent comes first
            if (\str_starts_with(haystack: $b, needle: $a . '/')) {
                return -1;
            }

            if (\str_starts_with(haystack: $a, needle: $b . '/')) {
                return 1;
            }

            // Get the top-level directories for comparison
            $topDirA = \explode(separator: '/', string: $a)[0];
            $topDirB = \explode(separator: '/', string: $b)[0];

            // If top-level directories are different, sort alphabetically
            if ($topDirA !== $topDirB) {
                return \strcmp(string1: $topDirA, string2: $topDirB);
            }

            // Count path segments (depth)
            $depthA = \substr_count(haystack: $a, needle: '/');
            $depthB = \substr_count(haystack: $b, needle: '/');

            // Sort by depth for paths with the same parent
            if ($depthA !== $depthB) {
                return $depthA <=> $depthB;
            }

            // If same depth and not parent-child, sort alphabetically
            return \strcmp(string1: $a, string2: $b);
        });

        return \array_unique(array: $normalized);
    }

    /**
     * Sort directories with parent directories appearing before their children,
     * and maintain original path separators
     *
     * @param array<string> $directories List of directory paths to sort
     * @return array<string> Sorted directory paths
     */
    public static function sortPreservingSeparators(array $directories): array
    {
        // Handle empty cases early
        if (empty($directories)) {
            return [];
        }

        // Create mapping of normalized paths to original paths
        $mapping = [];
        $normalized = [];

        foreach ($directories as $path) {
            // Make sure we handle Windows path separators consistently
            $normalizedPath = self::normalizePath($path);

            // Avoid duplicate keys in the mapping
            if (!isset($mapping[$normalizedPath])) {
                $mapping[$normalizedPath] = $path;
                $normalized[] = $normalizedPath;
            }
        }

        // Sort the normalized paths
        $sorted = self::sort($normalized);

        // Map back to original paths
        return \array_map(
            callback: static fn(string $normalizedPath): string => $mapping[$normalizedPath],
            array: $sorted,
        );
    }

    /**
     * Normalize a path to a standard format for comparison
     * - Converts backslashes to forward slashes
     * - Removes trailing slashes
     * - Handles Windows drive letters consistently
     *
     * @param string $path Path to normalize
     * @return string Normalized path
     */
    private static function normalizePath(string $path): string
    {
        // Replace Windows backslashes with forward slashes
        $path = \str_replace(search: '\\', replace: '/', subject: $path);

        // Remove trailing slashes
        $path = \rtrim(string: $path, characters: '/');

        // Normalize Windows drive letter format (if present)
        if (\preg_match(pattern: '/^[A-Z]:\//i', subject: $path)) {
            $path = \substr(string: $path, offset: 2); // Remove drive letter and colon (e.g., "C:")
        }

        return $path;
    }
}
