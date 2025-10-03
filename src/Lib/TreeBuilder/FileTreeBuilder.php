<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\TreeBuilder;

use Butschster\ContextGenerator\Lib\TreeBuilder\TreeRenderer\AsciiTreeRenderer;

/**
 * File tree builder for visualizing directory structures
 */
final readonly class FileTreeBuilder
{
    public function __construct(
        private TreeRendererInterface $renderer = new AsciiTreeRenderer(),
    ) {}

    /**
     * Build a file tree representation from a list of files
     *
     * @param array<string> $files List of file paths
     * @param string $basePath Base path for relative display
     * @param array<string, mixed> $options Additional options for rendering
     * @return string Text representation of the file tree
     */
    public function buildTree(
        iterable $files,
        string $basePath,
        array $options = [],
    ): string {
        $files = DirectorySorter::sortPreservingSeparators(directories: $files);


        // Get or create a renderer
        $renderer = $this->renderer;

        // Normalize file paths and remove base path prefix
        $normalizedFiles = [];
        foreach ($files as $file) {
            // Normalize both file and base path consistently across all platforms
            $normalizedFile = $this->normalizePath(path: $file);
            $normalizedBasePath = $this->normalizePath(path: $basePath);

            // Remove base path from file path to get the relative path
            $normalizedPath = \trim(string: \str_replace(search: $normalizedBasePath, replace: '', subject: $normalizedFile));

            // Additional cleaning (Windows can produce paths with mixed separators)
            if (!\str_starts_with(haystack: $normalizedPath, needle: '/')) {
                $normalizedPath = '/' . $normalizedPath;
            }

            $ext = \pathinfo(path: $normalizedPath, flags: PATHINFO_EXTENSION);
            $isDirectory = \is_dir(filename: $file) || $ext === '';

            $normalizedFiles[] = [
                'path' => $normalizedPath,
                'fullPath' => $file,
                'isDirectory' => $isDirectory,
            ];
        }


        // Sort files for consistent display
        \usort(array: $normalizedFiles, callback: static fn($a, $b) => $a['path'] <=> $b['path']);

        // Build tree structure with all files and directories
        $tree = [];
        foreach ($normalizedFiles as $fileInfo) {
            $parts = \array_filter(array: \explode(separator: '/', string: \trim(string: $fileInfo['path'], characters: '/')), callback: strlen(...));

            $this->addToTree(
                tree: $tree,
                parts: $parts,
                fullPath: $fileInfo['fullPath'],
                isDirectoryPath: $fileInfo['isDirectory'],
            );
        }

        // Generate tree representation using the renderer
        // Pass the includeFiles option to the renderer
        return $renderer->render($tree, $options);
    }

    /**
     * Add a path to the tree structure
     *
     * @param array<mixed> &$tree Tree structure
     * @param array<string> $parts Path parts
     * @param string $fullPath Full file path (for metadata access)
     * @param bool $isDirectoryPath Whether the path is a directory (not a file)
     */
    private function addToTree(array &$tree, array $parts, string $fullPath = '', bool $isDirectoryPath = false): void
    {
        $_current = &$tree;

        foreach ($parts as $index => $part) {
            if (!isset($_current[$part])) {
                // Determine if it's a directory:
                // - Either it's not the last segment of the path
                // - Or the entire path represents a directory
                $isDirectory = $index < \count(value: $parts) - 1 || $isDirectoryPath;

                // For directories, create an array for children
                // For files, store the full path for metadata access
                $_current[$part] = $isDirectory ? [] : $fullPath;
            }

            // Only continue traversing if this is a directory (array)
            if (\is_array(value: $_current[$part])) {
                $_current = &$_current[$part];
            }
        }
    }

    /**
     * Normalize a path to a standard format for comparison
     * - Converts backslashes to forward slashes
     * - Handles Windows drive letters consistently
     *
     * @param string $path Path to normalize
     * @return string Normalized path
     */
    private function normalizePath(string $path): string
    {
        // Replace Windows backslashes with forward slashes
        $path = \str_replace(search: '\\', replace: '/', subject: $path);

        // Normalize Windows drive letter format (if present)
        if (\preg_match(pattern: '/^[A-Z]:\//i', subject: $path)) {
            $path = \substr(string: $path, offset: 2); // Remove drive letter and colon (e.g., "C:")
        }

        return $path;
    }
}
