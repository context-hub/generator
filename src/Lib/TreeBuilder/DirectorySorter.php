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
            static fn(string $path): string => \rtrim(\str_replace('\\', '/', $path), '/'),
            \array_unique($directories),
        );

        // Early return for empty arrays or single item arrays (already sorted)
        if (\count($normalized) <= 1) {
            return $normalized;
        }

        // Sort by path segments count (depth) first, then alphabetically
        \usort($normalized, static function (string $a, string $b): int {
            // Count path segments (depth)
            $depthA = \substr_count($a, '/');
            $depthB = \substr_count($b, '/');

            // If one path is a direct parent of another, make sure the parent comes first
            if (\str_starts_with($b, $a . '/')) {
                return -1;
            }

            if (\str_starts_with($a, $b . '/')) {
                return 1;
            }

            // If paths have the same depth, sort alphabetically
            if ($depthA === $depthB) {
                return \strcmp($a, $b);
            }

            // Sort by depth
            return $depthA <=> $depthB;
        });

        return $normalized;
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
        // Create mapping of normalized paths to original paths
        $mapping = [];
        $normalized = [];

        foreach ($directories as $path) {
            $normalizedPath = \rtrim(\str_replace('\\', '/', $path), '/');
            $mapping[$normalizedPath] = $path;
            $normalized[] = $normalizedPath;
        }

        // Sort the normalized paths
        $sorted = self::sort($normalized);

        // Map back to original paths
        return \array_map(
            static fn(string $normalizedPath): string => $mapping[$normalizedPath],
            $sorted,
        );
    }
}
