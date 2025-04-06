<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Lib\Finder\FinderInterface;
use Butschster\ContextGenerator\Lib\Finder\FinderResult;
use Butschster\ContextGenerator\Lib\Git\GitClientInterface;
use Butschster\ContextGenerator\Lib\TreeBuilder\FileTreeBuilder;
use Butschster\ContextGenerator\Source\Fetcher\FilterableSourceInterface;
use Butschster\ContextGenerator\Source\GitDiff\Fetcher\CommitRangeParser;
use Butschster\ContextGenerator\Source\GitDiff\Fetcher\GitSourceFactory;
use Butschster\ContextGenerator\Source\GitDiff\Fetcher\GitSourceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * This finder extracts diffs from git commits, stashes, and other sources and applies filters to them
 */
final readonly class GitDiffFinder implements FinderInterface
{
    public function __construct(
        private GitSourceFactory $sourceFactory,
        private GitClientInterface $gitClient,
        private FileTreeBuilder $fileTreeBuilder,
        private CommitRangeParser $rangeParser,
        #[LoggerPrefix(prefix: 'git-diff-finder')]
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Find git commit diffs based on the given source configuration
     *
     * @param FilterableSourceInterface $source Source configuration with filter criteria
     * @param string $basePath Optional base path to normalize file paths in the tree view
     * @return FinderResult The result containing found diffs and tree view
     */
    public function find(FilterableSourceInterface $source, string $basePath = '', array $options = []): FinderResult
    {
        if (!$source instanceof GitDiffSource) {
            throw new \InvalidArgumentException('Source must be an instance of CommitDiffSource');
        }

        // Validate repository
        if (!$this->gitClient->isValidRepository($source->repository)) {
            throw new \RuntimeException(\sprintf('"%s" is not a valid Git repository', $source->repository));
        }

        // Get the commit range from the source
        $commitRange = $this->rangeParser->resolve($source->commit);

        $this->logger?->debug('Resolved commit range', [
            'original' => $source->commit,
            'resolved' => $commitRange,
        ]);

        // Get the appropriate Git source for this commit range
        $gitSource = $this->sourceFactory->create($commitRange);

        $this->logger?->debug('Selected Git source', [
            'source' => $gitSource::class,
        ]);

        // Create a temporary directory for the diffs
        $tempDir = \sys_get_temp_dir() . '/git-diff-' . \uniqid();
        \mkdir($tempDir, 0777, true);

        try {
            // Get file infos from the Git source
            $this->logger?->debug('Getting file infos from Git source');
            $fileInfos = $gitSource->createFileInfos($source->repository, $commitRange, $tempDir);

            if (empty($fileInfos)) {
                $this->logger?->info('No changes found for the commit range', [
                    'commitRange' => $commitRange,
                ]);
                return new FinderResult([], 'No changes found');
            }

            $this->logger?->debug('Found files', [
                'count' => \count($fileInfos),
            ]);

            // Apply filters if needed
            $fileInfos = $this->applyFilters($fileInfos, $source, $tempDir);

            $this->logger?->debug('After applying filters', [
                'count' => \count($fileInfos),
            ]);

            // Get file paths for tree view
            $filePaths = \array_map(
                static fn(SplFileInfo $fileInfo) => \method_exists($fileInfo, 'getOriginalPath')
                    ? $fileInfo->getOriginalPath()
                    : $fileInfo->getRelativePathname(),
                $fileInfos,
            );

            // Generate tree view
            $treeView = $this->generateTreeView($filePaths, $gitSource, $commitRange, $options);

            return new FinderResult(\array_values($fileInfos), $treeView);
        } catch (\Throwable $e) {
            $this->logger?->error('Error finding git diffs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
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

        $this->logger?->debug('Applying filters to file infos', [
            'fileCount' => \count($fileInfos),
            'name' => $source->name(),
            'path' => $source->path(),
            'notPath' => $source->notPath(),
            'contains' => $source->contains(),
            'notContains' => $source->notContains(),
        ]);

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
        $filtered = \array_filter($fileInfos, static function (SplFileInfo $fileInfo) use ($filteredPaths, $tempDir) {
            $relativePath = \str_replace($tempDir . '/', '', $fileInfo->getPathname());
            return isset($filteredPaths[$relativePath]);
        });

        $this->logger?->debug('Filter results', [
            'originalCount' => \count($fileInfos),
            'filteredCount' => \count($filtered),
        ]);

        return $filtered;
    }

    /**
     * Generate a tree view of the changed files
     *
     * @param array<string> $files List of file paths
     * @param GitSourceInterface $gitSource The Git source used for the commits
     * @param string|array $commitRange Commit range for the header
     * @return string Text representation of the file tree
     */
    private function generateTreeView(
        array $files,
        GitSourceInterface $gitSource,
        string|array $commitRange,
        array $options = [],
    ): string {
        if (empty($files)) {
            return "No changes found\n";
        }

        // Get a descriptive header from the Git source
        $treeHeader = $gitSource->formatReferenceForDisplay($commitRange) . "\n";

        // Build the tree
        $tree = $this->fileTreeBuilder->buildTree($files, '', $options);

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
