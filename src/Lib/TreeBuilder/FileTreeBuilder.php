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
        // Get or create a renderer
        $renderer = $this->renderer;

        // Normalize file paths and remove base path prefix
        $normalizedFiles = [];
        foreach ($files as $file) {
            $normalizedPath = \trim(\str_replace($basePath, '', $file));
            if (!\str_starts_with($normalizedPath, '/')) {
                $normalizedPath = '/' . $normalizedPath;
            }
            $normalizedFiles[] = $normalizedPath;
        }

        // Sort files for consistent display
        \sort($normalizedFiles);

        // Build tree structure
        $tree = [];
        foreach ($normalizedFiles as $path) {
            $parts = \explode('/', \trim($path, '/'));
            $this->addToTree($tree, $parts, $basePath . '/' . $path);
        }

        // Generate tree representation using the renderer
        return $renderer->render($tree, $options);
    }

    /**
     * Add a path to the tree structure
     *
     * @param array<mixed> &$tree Tree structure
     * @param array<string> $parts Path parts
     * @param string $fullPath Full file path (for metadata access)
     */
    private function addToTree(array &$tree, array $parts, string $fullPath = ''): void
    {
        $current = &$tree;
        $path = '';

        foreach ($parts as $index => $part) {
            $path .= '/' . $part;

            if (!isset($current[$part])) {
                $isDirectory = $index < \count($parts) - 1;

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
