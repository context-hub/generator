<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\File;

use Butschster\ContextGenerator\Config\Exclude\ExcludeRegistryInterface;
use Butschster\ContextGenerator\Lib\Finder\FinderInterface;
use Butschster\ContextGenerator\Lib\Finder\FinderResult;
use Butschster\ContextGenerator\Lib\TreeBuilder\FileTreeBuilder;
use Butschster\ContextGenerator\Source\Fetcher\FilterableSourceInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Implementation of FinderInterface using Symfony's Finder component
 */
final readonly class SymfonyFinder implements FinderInterface
{
    public function __construct(
        private ExcludeRegistryInterface $excludeRegistry,
        private FileTreeBuilder $fileTreeBuilder = new FileTreeBuilder(),
    ) {}

    /**
     * Find files based on the given source configuration
     *
     * @param FilterableSourceInterface $source Source configuration with filter criteria
     * @param string $basePath Optional base path to normalize file paths in the tree view
     * @param array $options Additional options for the finder
     * @return FinderResult The result containing found files and tree view
     */
    public function find(FilterableSourceInterface $source, string $basePath = '', array $options = []): FinderResult
    {
        $finder = new Finder();
        $finder->files();

        // Configure in() with directories and files
        $directories = $source->in();
        $files = $source->files();

        if (!empty($directories)) {
            $finder->in(dirs: $directories);
        }

        if (!empty($files)) {
            $finder->append(iterator: $files);
        }

        // Configure name pattern for file matching
        $namePattern = $source->name();
        if ($namePattern !== null) {
            $finder->name(patterns: $namePattern);
        }

        // Configure path pattern
        $pathPattern = $source->path();
        if ($pathPattern !== null) {
            $finder->path(patterns: $pathPattern);
        }

        // Configure notPath pattern
        $notPathPattern = $source->notPath();
        if ($notPathPattern !== null) {
            $finder->notPath(patterns: $notPathPattern);
        }

        // Configure contains pattern
        $containsPattern = $source->contains();
        if ($containsPattern !== null) {
            $finder->contains(patterns: $containsPattern);
        }

        // Configure notContains pattern
        $notContainsPattern = $source->notContains();
        if ($notContainsPattern !== null) {
            $finder->notContains(patterns: $notContainsPattern);
        }

        // Configure size constraints
        $sizeConstraints = $source->size();
        if ($sizeConstraints !== null) {
            $finder->size(sizes: $sizeConstraints);
        }

        // Configure date constraints
        $dateConstraints = $source->date();
        if ($dateConstraints !== null) {
            $finder->date(dates: $dateConstraints);
        }

        // Configure ignoreUnreadableDirs
        if ($source->ignoreUnreadableDirs()) {
            $finder->ignoreUnreadableDirs();
        }

        // Apply depth constraint if maxDepth is set
        if (isset($options['maxDepth']) && $options['maxDepth'] > 0) {
            $finder->depth(levels: '<= ' . $options['maxDepth']);
        }

        $finder->sortByName();

        // Check for maxFiles limit
        $maxFiles = $source->maxFiles();
        $hasLimit = $maxFiles !== null && $maxFiles > 0;

        // Generate tree view (always on all files for consistency)
        $treeView = $this->generateTreeView(finder: $finder, basePath: $basePath, options: $options);

        // Get files with limit if needed
        if ($hasLimit) {
            $limitedFiles = [];
            $count = 0;

            foreach ($finder as $file) {
                $limitedFiles[] = $file;
                $count++;

                if ($count >= $maxFiles) {
                    break;
                }
            }

            return new FinderResult(
                files: $limitedFiles,
                treeView: $treeView,
            );
        }

        // No limit, filter out excluded files
        $files = [];
        foreach ($finder as $file) {
            // Skip files that would be excluded by path patterns
            if ($this->shouldExcludeFile(filePath: $this->getPath(file: $file, basePath: $basePath))) {
                continue;
            }

            $files[] = $file;
        }

        // Return filtered files
        return new FinderResult(
            files: $files,
            treeView: $treeView,
        );
    }

    /**
     * Generate a tree view of the found files
     *
     * @param Finder $finder The Symfony Finder instance with results
     * @param string $basePath Optional base path to normalize file paths
     * @param array $options Additional options for tree view generation
     * @return string Text representation of the file tree
     */
    private function generateTreeView(Finder $finder, string $basePath, array $options): string
    {
        $filePaths = [];

        foreach ($finder as $file) {
            // Skip excluded files in tree view
            if ($this->shouldExcludeFile(filePath: $this->getPath(file: $file, basePath: $basePath))) {
                continue;
            }

            $filePaths[] = $file->getRealPath();
        }

        if (empty($filePaths)) {
            return "No files found.\n";
        }

        return $this->fileTreeBuilder->buildTree(files: $filePaths, basePath: $basePath, options: $options);
    }

    /**
     * Check if a file should be excluded based on global exclusion patterns
     */
    private function shouldExcludeFile(string $filePath): bool
    {
        return $this->excludeRegistry->shouldExclude($filePath);
    }

    private function getPath(SplFileInfo|\SplFileInfo $file, string $basePath)
    {
        if ($file instanceof SplFileInfo) {
            return $file->getRelativePathname();
        }

        return \ltrim(string: \str_replace(search: $basePath, replace: '', subject: $file->getRealPath()), characters: '/');
    }
}
