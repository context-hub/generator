<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff;

use Butschster\ContextGenerator\Fetcher\FilterableSourceInterface;
use Butschster\ContextGenerator\Lib\Finder\FinderInterface;
use Butschster\ContextGenerator\Lib\Finder\FinderResult;
use Butschster\ContextGenerator\Lib\TreeBuilder\FileTreeBuilder;
use Butschster\ContextGenerator\Source\GitDiff\Fetcher\CommitRangeParser;
use Butschster\ContextGenerator\Source\GitDiff\Fetcher\GitSourceFactory;
use Butschster\ContextGenerator\Source\GitDiff\Fetcher\GitSourceInterface;
use Butschster\ContextGenerator\Source\GitDiff\Fetcher\Source\CommitGitSource;
use Butschster\ContextGenerator\Source\GitDiff\Fetcher\Source\FileAtCommitGitSource;
use Butschster\ContextGenerator\Source\GitDiff\Fetcher\Source\StagedGitSource;
use Butschster\ContextGenerator\Source\GitDiff\Fetcher\Source\StashGitSource;
use Butschster\ContextGenerator\Source\GitDiff\Fetcher\Source\TimeRangeGitSource;
use Butschster\ContextGenerator\Source\GitDiff\Fetcher\Source\UnstagedGitSource;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Finder for git commit diffs
 *
 * This finder extracts diffs from git commits, stashes, and other sources and applies filters to them
 */
final readonly class CommitDiffFinder implements FinderInterface
{
    private GitSourceFactory $sourceFactory;

    public function __construct(
        private FileTreeBuilder $fileTreeBuilder = new FileTreeBuilder(),
        private CommitRangeParser $rangeParser = new CommitRangeParser(),
    ) {
        // Initialize the source factory with all available sources
        $this->sourceFactory = new GitSourceFactory([
            new StashGitSource(),
            new CommitGitSource(),
            new StagedGitSource(),
            new UnstagedGitSource(),
            new TimeRangeGitSource(),
            new FileAtCommitGitSource(),
        ]);
    }

    /**
     * Find git commit diffs based on the given source configuration
     *
     * @param FilterableSourceInterface $source Source configuration with filter criteria
     * @param string $basePath Optional base path to normalize file paths in the tree view
     * @return FinderResult The result containing found diffs and tree view
     */
    public function find(FilterableSourceInterface $source, string $basePath = ''): FinderResult
    {
        if (!$source instanceof CommitDiffSource) {
            throw new \InvalidArgumentException('Source must be an instance of CommitDiffSource');
        }

        // Ensure the repository exists
        if (!\is_dir($source->repository)) {
            throw new \RuntimeException(\sprintf('Git repository "%s" does not exist', $source->repository));
        }

        // Get the commit range from the source
        $commitRange = $this->rangeParser->resolve($source->commit);

        // Resolve the commit reference using the range parser
        // Get the appropriate Git source for this commit range
        $gitSource = $this->sourceFactory->create($commitRange);

        // Create a temporary directory for the diffs
        $tempDir = \sys_get_temp_dir() . '/git-diff-' . \uniqid();
        \mkdir($tempDir, 0777, true);

        try {
            // Get file infos from the Git source
            $fileInfos = $gitSource->createFileInfos($source->repository, $commitRange, $tempDir);

            if (empty($fileInfos)) {
                return new FinderResult(new \ArrayIterator([]), 'No changes found');
            }

            // Apply filters if needed
            $fileInfos = $this->applyFilters($fileInfos, $source, $tempDir);

            // Get file paths for tree view
            $filePaths = \array_map(
                static fn(SplFileInfo $fileInfo) => \method_exists($fileInfo, 'getOriginalPath')
                    ? $fileInfo->getOriginalPath()
                    : $fileInfo->getRelativePathname(),
                $fileInfos,
            );

            // Generate tree view
            $treeView = $this->generateTreeView($filePaths, $gitSource, $commitRange);

            return new FinderResult(new \ArrayIterator($fileInfos), $treeView);
        } finally {
            // Clean up the temporary directory
            $this->removeDirectory($tempDir);
        }
    }

    /**
     * Apply filters to the file infos
     *
     * @param array<SplFileInfo> $fileInfos
     * @return array<SplFileInfo>
     */
    private function applyFilters(array $fileInfos, FilterableSourceInterface $source, string $tempDir): array
    {
        if (empty($fileInfos)) {
            return [];
        }

        $finder = new Finder();
        $finder->in($tempDir);

        // Apply name filter
        if ($source->name() !== null) {
            $finder->name($source->name());
        }

        // Apply path filter
        if ($source->path() !== null) {
            $finder->path($source->path());
        }

        // Apply notPath filter
        if ($source->notPath() !== null) {
            $finder->notPath($source->notPath());
        }

        // Apply contains filter
        if ($source->contains() !== null) {
            $finder->contains($source->contains());
        }

        // Apply notContains filter
        if ($source->notContains() !== null) {
            $finder->notContains($source->notContains());
        }

        // Get the filtered files
        $filteredPaths = [];
        foreach ($finder as $file) {
            $relativePath = \str_replace($tempDir . '/', '', $file->getPathname());
            $filteredPaths[$relativePath] = true;
        }

        // Filter the file infos
        return \array_filter($fileInfos, static function (SplFileInfo $fileInfo) use ($filteredPaths, $tempDir) {
            $relativePath = \str_replace($tempDir . '/', '', $fileInfo->getPathname());
            return isset($filteredPaths[$relativePath]);
        });
    }

    /**
     * Generate a tree view of the changed files
     *
     * @param array<string> $files List of file paths
     * @param GitSourceInterface $gitSource The Git source used for the commits
     * @param string|array $commitRange Commit range for the header
     * @return string Text representation of the file tree
     */
    private function generateTreeView(array $files, GitSourceInterface $gitSource, string|array $commitRange): string
    {
        if (empty($files)) {
            return "No changes found\n";
        }

        // Get a descriptive header from the Git source
        $treeHeader = $gitSource->formatReferenceForDisplay($commitRange) . "\n";

        // Build the tree
        $tree = $this->fileTreeBuilder->buildTree($files, '');

        return $treeHeader . $tree;
    }

    /**
     * Recursively remove a directory
     */
    private function removeDirectory(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        $files = \array_diff(\scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            \is_dir($path) ? $this->removeDirectory($path) : \unlink($path);
        }

        \rmdir($dir);
    }
}
