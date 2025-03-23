<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\File;

use Butschster\ContextGenerator\Fetcher\FilterableSourceInterface;
use Butschster\ContextGenerator\Lib\Finder\FinderInterface;
use Butschster\ContextGenerator\Lib\Finder\FinderResult;
use Butschster\ContextGenerator\Lib\TreeBuilder\FileTreeBuilder;
use Symfony\Component\Finder\Finder;

/**
 * Implementation of FinderInterface using Symfony's Finder component
 */
final readonly class SymfonyFinder implements FinderInterface
{
    public function __construct(
        private FileTreeBuilder $fileTreeBuilder = new FileTreeBuilder(),
    ) {}

    /**
     * Find files based on the given source configuration
     *
     * @param FilterableSourceInterface $source Source configuration with filter criteria
     * @param string $basePath Optional base path to normalize file paths in the tree view
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
            $finder->in($directories);
        }

        if (!empty($files)) {
            $finder->append($files);
        }

        // Configure name pattern for file matching
        $namePattern = $source->name();
        if ($namePattern !== null) {
            $finder->name($namePattern);
        }

        // Configure path pattern
        $pathPattern = $source->path();
        if ($pathPattern !== null) {
            $finder->path($pathPattern);
        }

        // Configure notPath pattern
        $notPathPattern = $source->notPath();
        if ($notPathPattern !== null) {
            $finder->notPath($notPathPattern);
        }

        // Configure contains pattern
        $containsPattern = $source->contains();
        if ($containsPattern !== null) {
            $finder->contains($containsPattern);
        }

        // Configure notContains pattern
        $notContainsPattern = $source->notContains();
        if ($notContainsPattern !== null) {
            $finder->notContains($notContainsPattern);
        }

        // Configure size constraints
        $sizeConstraints = $source->size();
        if ($sizeConstraints !== null) {
            $finder->size($sizeConstraints);
        }

        // Configure date constraints
        $dateConstraints = $source->date();
        if ($dateConstraints !== null) {
            $finder->date($dateConstraints);
        }

        // Configure ignoreUnreadableDirs
        if ($source->ignoreUnreadableDirs()) {
            $finder->ignoreUnreadableDirs();
        }

        $finder->sortByName();

        return new FinderResult(
            files: \array_values(\iterator_to_array($finder->getIterator())),
            treeView: $this->generateTreeView($finder, $basePath, $options),
        );
    }

    /**
     * Generate a tree view of the found files
     *
     * @param Finder $finder The Symfony Finder instance with results
     * @param string $basePath Optional base path to normalize file paths
     * @return string Text representation of the file tree
     */
    private function generateTreeView(Finder $finder, string $basePath, array $options): string
    {
        $filePaths = [];

        foreach ($finder as $file) {
            $filePaths[] = $file->getRealPath();
        }

        if (empty($filePaths)) {
            return "No files found.\n";
        }

        return $this->fileTreeBuilder->buildTree($filePaths, $basePath, $options);
    }
}
