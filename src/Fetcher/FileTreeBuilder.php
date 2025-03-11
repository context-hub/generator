<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Fetcher;

/**
 * File tree builder for visualizing directory structures
 */
final readonly class FileTreeBuilder
{
    /**
     * Build a file tree representation from a list of files
     *
     * @param array<string> $files List of file paths
     * @param string $basePath Base path for relative display
     * @return string Text representation of the file tree
     */
    public function buildTree(iterable $files, string $basePath): string
    {
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
            $this->addToTree($tree, $parts);
        }

        // Generate tree representation
        return $this->renderTree($tree);
    }

    /**
     * Add a path to the tree structure
     *
     * @param array<mixed> &$tree Tree structure
     * @param array<string> $parts Path parts
     */
    private function addToTree(array &$tree, array $parts): void
    {
        $current = &$tree;

        foreach ($parts as $part) {
            if (!isset($current[$part])) {
                $current[$part] = [];
            }
            $current = &$current[$part];
        }
    }

    /**
     * Render tree structure as text
     *
     * @param array<mixed> $tree Tree structure
     * @param string $prefix Current line prefix
     * @param bool $isLast Whether current node is the last child
     * @return string Text representation of the tree
     */
    private function renderTree(array $tree, string $prefix = '', bool $isLast = true): string
    {
        $result = '';
        $count = \count($tree);
        $i = 0;

        foreach ($tree as $name => $children) {
            $i++;
            $isLastItem = ($i === $count);

            // Add current node
            $result .= $prefix . ($isLast ? '└── ' : '├── ') . $name . PHP_EOL;

            // Add children
            if (!empty($children)) {
                $newPrefix = $prefix . ($isLast ? '    ' : '│   ');
                $result .= $this->renderTree($children, $newPrefix, $isLastItem);
            }
        }

        return $result;
    }
}
