<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Tree;

use Butschster\ContextGenerator\Fetcher\FilterableSourceInterface;
use Butschster\ContextGenerator\Lib\Finder\FinderInterface;
use Butschster\ContextGenerator\Lib\Finder\FinderResult;
use Symfony\Component\Finder\Finder;

/**
 * Finder implementation for tree sources
 */
final readonly class TreeFinder implements FinderInterface
{
    /**
     * Find files and generate a tree structure based on source configuration
     *
     * @param FilterableSourceInterface $source Source configuration
     * @param string $basePath Base path for normalization
     * @return FinderResult Result containing tree structure data
     */
    public function find(FilterableSourceInterface $source, string $basePath = ''): FinderResult
    {
        if (!$source instanceof TreeSource) {
            throw new \InvalidArgumentException(
                \sprintf('Source must be an instance of TreeSource, %s given', $source::class),
            );
        }

        $directories = $source->in();
        $files = $source->files();

        if (empty($directories) && empty($files)) {
            return new FinderResult(
                files: [],
                treeView: "No directories or files specified.\n",
            );
        }

        $tree = [];
        $allFiles = [];

        // Process directories
        if (!empty($directories)) {
            $finder = new Finder();
            $finder->files();
            $finder->in($directories);

            // Apply filters
            $namePattern = $source->name();
            if ($namePattern !== null) {
                $finder->name($namePattern);
            }

            $pathPattern = $source->path();
            if ($pathPattern !== null) {
                $finder->path($pathPattern);
            }

            $notPathPattern = $source->notPath();
            if ($notPathPattern !== null) {
                $finder->notPath($notPathPattern);
            }

            $containsPattern = $source->contains();
            if ($containsPattern !== null) {
                $finder->contains($containsPattern);
            }

            $notContainsPattern = $source->notContains();
            if ($notContainsPattern !== null) {
                $finder->notContains($notContainsPattern);
            }

            if ($source->ignoreUnreadableDirs()) {
                $finder->ignoreUnreadableDirs();
            }

            // Apply depth limit if set
            if ($source->maxDepth > 0) {
                $finder->depth("< {$source->maxDepth}");
            }

            $finder->sortByName();

            // Build the tree structure
            foreach ($finder as $file) {
                $allFiles[] = $file;

                if ($source->includeFiles) {
                    $relativePath = \trim(\str_replace($basePath, '', $file->getPath()));
                    $fileName = $file->getFilename();

                    $this->addToTree($tree, $relativePath, $fileName, $basePath);
                } else {
                    // For directories-only mode, just gather the directories
                    $relativePath = \trim(\str_replace($basePath, '', $file->getPath()));
                    $this->addToTree($tree, $relativePath, null, $basePath);
                }
            }
        }

        // Process individual files
        if (!empty($files)) {
            foreach ($files as $filePath) {
                $relativePath = \dirname(\trim(\str_replace($basePath, '', $filePath)));
                $fileName = \basename($filePath);

                $allFiles[] = new \SplFileInfo($filePath);
                $this->addToTree($tree, $relativePath, $fileName, $basePath);
            }
        }

        // Sort the tree for consistent display
        $this->sortTree($tree);

        return new FinderResult(
            files: $allFiles,
            treeView: '',
        );
    }

    /**
     * Add a file or directory to the tree structure
     *
     * @param array<string, mixed> &$tree Tree structure to modify
     * @param string $path Directory path
     * @param string|null $fileName Optional filename (null for directory-only)
     * @param string $basePath Base path for normalization
     */
    private function addToTree(array &$tree, string $path, ?string $fileName, string $basePath): void
    {
        // Normalize path
        $path = \ltrim($path, '/');

        // Build directory structure
        $current = &$tree;

        if (!empty($path)) {
            $parts = \explode('/', $path);

            foreach ($parts as $part) {
                if (empty($part)) {
                    continue;
                }

                if (!isset($current[$part])) {
                    $current[$part] = [];
                }

                $current = &$current[$part];
            }
        }

        // Add file to the current directory if provided
        if ($fileName !== null) {
            $current[$fileName] = null;
        }
    }

    /**
     * Sort the tree structure recursively
     *
     * @param array<string, mixed> &$tree Tree structure to sort
     */
    private function sortTree(array &$tree): void
    {
        // Sort directories first, then files
        $directories = [];
        $files = [];

        foreach ($tree as $name => $children) {
            if (\is_array($children)) {
                $directories[$name] = $children;
                $this->sortTree($directories[$name]);
            } else {
                $files[$name] = $children;
            }
        }

        // Sort by name
        \ksort($directories);
        \ksort($files);

        // Merge and replace original tree
        $tree = \array_merge($directories, $files);
    }
}
