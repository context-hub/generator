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
        $files = DirectorySorter::sortPreservingSeparators($files);

        // Get or create a renderer
        $renderer = $this->renderer;

        // Normalize file paths and remove base path prefix
        $normalizedFiles = [];
        foreach ($files as $file) {
            $normalizedPath = \trim(\str_replace($basePath, '', $file));
            if (!\str_starts_with($normalizedPath, '/')) {
                $normalizedPath = '/' . $normalizedPath;
            }

            // Check if this path is a directory
            $isDirectory = \is_dir($file);

            $normalizedFiles[] = [
                'path' => $normalizedPath,
                'fullPath' => $file,
                'isDirectory' => $isDirectory,
            ];
        }

        // Sort files for consistent display
        \usort($normalizedFiles, static fn($a, $b) => $a['path'] <=> $b['path']);

        // Build tree structure with all files and directories
        $tree = [];
        foreach ($normalizedFiles as $fileInfo) {
            $parts = \explode('/', \trim($fileInfo['path'], '/'));
            $this->addToTree(
                $tree,
                $parts,
                $fileInfo['fullPath'],
                $fileInfo['isDirectory'],
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
        $current = &$tree;

        foreach ($parts as $index => $part) {

            if (!isset($current[$part])) {
                // Determine if it's a directory:
                // - Either it's not the last segment of the path
                // - Or the entire path represents a directory
                $isDirectory = $index < \count($parts) - 1 || $isDirectoryPath;

                // For directories, create an array for children
                // For files, store the full path for metadata access
                $current[$part] = $isDirectory ? [] : $fullPath;
            }

            // Only continue traversing if this is a directory (array)
            if (\is_array($current[$part])) {
                $current = &$current[$part];
            }
        }
    }
}
