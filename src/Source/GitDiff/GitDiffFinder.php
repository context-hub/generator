<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Lib\Finder\FinderInterface;
use Butschster\ContextGenerator\Lib\Finder\FinderResult;
use Butschster\ContextGenerator\Lib\TreeBuilder\FileTreeBuilder;
use Butschster\ContextGenerator\Source\Fetcher\FilterableSourceInterface;
use Butschster\ContextGenerator\Source\GitDiff\Fetcher\CommitRangeParser;
use Butschster\ContextGenerator\Source\GitDiff\Fetcher\GitSourceFactory;
use Butschster\ContextGenerator\Source\GitDiff\Fetcher\GitSourceInterface;
use Psr\Log\LoggerInterface;
use Spiral\Files\FilesInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * This finder extracts diffs from git commits, stashes, and other sources and applies filters to them
 */
final readonly class GitDiffFinder implements FinderInterface
{
    public function __construct(
        private FilesInterface $files,
        private GitSourceFactory $sourceFactory,
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
            throw new \InvalidArgumentException(message: 'Source must be an instance of CommitDiffSource');
        }

        // Get the commit range from the source
        $commitRange = $this->rangeParser->resolve(commitRange: $source->commit);

        $this->logger?->debug('Resolved commit range', [
            'original' => $source->commit,
            'resolved' => $commitRange,
        ]);

        // Get the appropriate Git source for this commit range
        $gitSource = $this->sourceFactory->create(commitReference: $commitRange);

        $this->logger?->debug('Selected Git source', [
            'source' => $gitSource::class,
        ]);

        // Create a temporary directory for the diffs
        $tempDir = FSPath::temp()->join('git-diff-' . \uniqid())->toString();
        $this->files->ensureDirectory($tempDir);

        try {
            // Get file infos from the Git source
            $this->logger?->debug('Getting file infos from Git source');
            $fileInfos = $gitSource->createFileInfos($source->repository, $commitRange, $tempDir);

            if (empty($fileInfos)) {
                $this->logger?->info('No changes found for the commit range', [
                    'commitRange' => $commitRange,
                ]);
                return new FinderResult(files: [], treeView: 'No changes found');
            }

            $this->logger?->debug('Found files', [
                'count' => \count(value: $fileInfos),
            ]);

            // Apply filters if needed
            $fileInfos = $this->applyFilters(fileInfos: $fileInfos, source: $source, tempDir: $tempDir);

            $this->logger?->debug('After applying filters', [
                'count' => \count(value: $fileInfos),
            ]);

            // Get file paths for tree view
            $filePaths = \array_map(
                callback: static fn(SplFileInfo $fileInfo)
                    => \method_exists(object_or_class: $fileInfo, method: 'getOriginalPath')
                    ? $fileInfo->getOriginalPath()
                    : $fileInfo->getRelativePathname(),
                array: $fileInfos,
            );

            // Generate tree view
            $treeView = $this->generateTreeView(files: $filePaths, gitSource: $gitSource, commitRange: $commitRange, options: $options);

            return new FinderResult(files: \array_values(array: $fileInfos), treeView: $treeView);
        } catch (\Throwable $e) {
            $this->logger?->error('Error finding git diffs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        } finally {
            $this->files->deleteDirectory($tempDir);
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
            'fileCount' => \count(value: $fileInfos),
            'name' => $source->name(),
            'path' => $source->path(),
            'notPath' => $source->notPath(),
            'contains' => $source->contains(),
            'notContains' => $source->notContains(),
        ]);

        $finder = new Finder();
        $finder->in(dirs: $tempDir);

        // Apply name filter
        if ($source->name() !== null) {
            $finder->name(patterns: $source->name());
        }

        // Apply path filter
        if ($source->path() !== null) {
            $finder->path(patterns: $source->path());
        }

        // Apply notPath filter
        if ($source->notPath() !== null) {
            $finder->notPath(patterns: $source->notPath());
        }

        // Apply contains filter
        if ($source->contains() !== null) {
            $finder->contains(patterns: $source->contains());
        }

        // Apply notContains filter
        if ($source->notContains() !== null) {
            $finder->notContains(patterns: $source->notContains());
        }

        // Get the filtered files
        $filteredPaths = [];
        foreach ($finder as $file) {
            $relativePath = FSPath::create(path: $file->getPathname())->trim(path: $tempDir)->toString();

            $filteredPaths[$relativePath] = true;
        }

        // Filter the file infos
        $filtered = \array_filter(array: $fileInfos, callback: static function (SplFileInfo $fileInfo) use ($filteredPaths, $tempDir) {
            $relativePath = FSPath::create(path: $fileInfo->getPathname())->trim(path: $tempDir)->toString();
            return isset($filteredPaths[$relativePath]);
        });

        $this->logger?->debug('Filter results', [
            'originalCount' => \count(value: $fileInfos),
            'filteredCount' => \count(value: $filtered),
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
        $tree = $this->fileTreeBuilder->buildTree(files: $files, basePath: '', options: $options);

        return $treeHeader . $tree;
    }
}
