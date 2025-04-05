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
            static fn(string $path): string => self::normalizePath($path),
            \array_unique((array) $directories),
        );

        // Early return for empty arrays or single item arrays (already sorted)
        if (\count($normalized) <= 1) {
            return $normalized;
        }

        // First sort alphabetically to ensure consistent ordering
        \sort($normalized);

        // Then re-sort to ensure parent directories appear before their children
        \usort($normalized, static function (string $a, string $b): int {
            // If one path is a direct parent of another, make sure the parent comes first
            if (\str_starts_with($b, $a . '/')) {
                return -1;
            }

            if (\str_starts_with($a, $b . '/')) {
                return 1;
            }

            // Get the top-level directories for comparison
            $topDirA = \explode('/', $a)[0];
            $topDirB = \explode('/', $b)[0];

            // If top-level directories are different, sort alphabetically
            if ($topDirA !== $topDirB) {
                return \strcmp($topDirA, $topDirB);
            }

            // Count path segments (depth)
            $depthA = \substr_count($a, '/');
            $depthB = \substr_count($b, '/');

            // Sort by depth for paths with the same parent
            if ($depthA !== $depthB) {
                return $depthA <=> $depthB;
            }

            // If same depth and not parent-child, sort alphabetically
            return \strcmp($a, $b);
        });

        return \array_unique($normalized);
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
            static fn(string $normalizedPath): string => $mapping[$normalizedPath],
            $sorted,
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
        $path = \str_replace('\\', '/', $path);

        // Remove trailing slashes
        $path = \rtrim($path, '/');

        // Normalize Windows drive letter format (if present)
        if (\preg_match('/^[A-Z]:\//i', $path)) {
            $path = \substr($path, 2); // Remove drive letter and colon (e.g., "C:")
        }

        return $path;
    }
}
