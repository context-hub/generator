<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Fetcher\Finder;

namespace Butschster\ContextGenerator\Fetcher\Finder;

use Butschster\ContextGenerator\Fetcher\FileTreeBuilder;
use Butschster\ContextGenerator\Fetcher\FilterableSourceInterface;
use Butschster\ContextGenerator\Fetcher\FinderInterface;
use Butschster\ContextGenerator\Fetcher\Git\CommitRangeParser;
use Butschster\ContextGenerator\Source\CommitDiffSource;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Finder for git commit diffs
 *
 * This finder extracts diffs from git commits and applies filters to them
 */
final readonly class CommitDiffFinder implements FinderInterface
{
    public function __construct(
        private FileTreeBuilder $fileTreeBuilder = new FileTreeBuilder(),
        private CommitRangeParser $rangeParser = new CommitRangeParser(),
    ) {}

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
        // The CommitDiffSource now returns an already resolved range from the parser
        $commitRange = $this->formatCommitRange($source->commit);

        // Get the list of changed files
        $changedFiles = $this->getChangedFiles($source->repository, $commitRange);

        if (empty($changedFiles)) {
            return new FinderResult(new \ArrayIterator([]), 'No changes found');
        }

        // Create a temporary directory for the diffs
        $tempDir = \sys_get_temp_dir() . '/git-diff-' . \uniqid();
        \mkdir($tempDir, 0777, true);

        try {
            // Write each diff to a temporary file
            $fileInfos = [];
            $filePaths = [];

            foreach ($changedFiles as $file) {
                // Get the diff for this file
                $diff = $this->getFileDiff($source->repository, $commitRange, $file);

                if (empty($diff)) {
                    continue;
                }

                // Create the temporary file
                $tempFile = $tempDir . '/' . $file;
                $tempDirname = \dirname($tempFile);

                if (!\is_dir($tempDirname)) {
                    \mkdir($tempDirname, 0777, true);
                }

                \file_put_contents($tempFile, $diff);

                // Add to file paths for tree generation
                $filePaths[] = $file;

                // Create a file info object with additional metadata
                $fileInfos[] = new class($tempFile, $file, $diff) extends SplFileInfo {
                    private readonly string $originalPath;

                    public function __construct(
                        string $tempFile,
                        string $originalPath,
                        private readonly string $diffContent,
                    ) {
                        parent::__construct($tempFile, \dirname($originalPath), $originalPath);
                        $this->originalPath = $originalPath;
                    }

                    public function getOriginalPath(): string
                    {
                        return $this->originalPath;
                    }

                    public function getDiffContent(): string
                    {
                        return $this->diffContent;
                    }

                    public function getContents(): string
                    {
                        return $this->diffContent;
                    }
                };
            }

            // Apply filters if needed
            if (!empty($fileInfos)) {
                $fileInfos = $this->applyFilters($fileInfos, $source, $tempDir);

                // Update file paths after filtering
                $filePaths = \array_map(
                    static fn(SplFileInfo $fileInfo) => \method_exists($fileInfo, 'getOriginalPath')
                        ? $fileInfo->getOriginalPath()
                        : $fileInfo->getRelativePathname(),
                    $fileInfos,
                );
            }
        } finally {
            // Clean up the temporary directory
            $this->removeDirectory($tempDir);
        }

        // Generate tree view using the FileTreeBuilder
        $treeView = $this->generateTreeView($filePaths, $commitRange);

        return new FinderResult(new \ArrayIterator($fileInfos), $treeView);
    }

    /**
     * Format the commit range for use with git commands
     */
    private function formatCommitRange(string|array $commitRange): string
    {
        $commitRange = $this->rangeParser->resolve($commitRange);

        // Handle special cases
        if ($commitRange === '') {
            return '--cached'; // This will get unstaged changes in the index
        }

        // Handle array of commit ranges
        if (\is_array($commitRange)) {
            return \implode(' ', $commitRange);
        }

        return $commitRange;
    }

    /**
     * Get the list of changed files in the commit range
     *
     * @return array<string>
     */
    private function getChangedFiles(string $repository, string $commitRange): array
    {
        $currentDir = \getcwd();
        $files = [];

        try {
            // Change to the repository directory
            \chdir($repository);

            // Check if the command contains `--since=` format, which needs a different approach
            if (\str_contains($commitRange, '--since=')) {
                $command = 'git log ' . $commitRange . ' --name-only --pretty=format:""';
                $output = [];
                \exec($command, $output, $returnCode);

                if ($returnCode !== 0) {
                    throw new \RuntimeException(
                        \sprintf('Failed to get changed files for commit range "%s"', $commitRange),
                    );
                }

                // Remove empty lines and duplicates
                $files = \array_unique(\array_filter($output));
                return $files;
            }

            // Get the list of changed files - handle unstaged and special formats differently
            if ($commitRange === '--cached') {
                $command = 'git diff --name-only --cached';
            } elseif (\str_contains($commitRange, ' -- ')) {
                // Handle specific file in commit format: abc1234 -- path/to/file.php
                [$commit, $path] = \explode(' -- ', $commitRange, 2);
                $command = \sprintf(
                    'git show --name-only %s -- %s',
                    \escapeshellarg($commit),
                    \escapeshellarg($path),
                );
            } else {
                $command = \sprintf('git diff --name-only %s', \escapeshellarg($commitRange));
            }

            $output = [];
            \exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \RuntimeException(
                    \sprintf('Failed to get changed files for commit range "%s"', $commitRange),
                );
            }

            $files = $output;
        } finally {
            // Restore the original directory
            \chdir($currentDir);
        }

        return $files;
    }

    /**
     * Get the diff for a specific file
     */
    private function getFileDiff(string $repository, string $commitRange, string $file): string
    {
        $currentDir = \getcwd();
        $diff = '';

        try {
            // Change to the repository directory
            \chdir($repository);

            // Check if the command contains `--since=` format, which needs a different approach
            if (\str_contains($commitRange, '--since=')) {
                $command = \sprintf(
                    'git log %s -p -- %s',
                    \escapeshellarg($commitRange),
                    \escapeshellarg($file),
                );
                $output = [];
                \exec($command, $output, $returnCode);

                if ($returnCode === 0) {
                    $diff = \implode("\n", $output);
                }

                return $diff;
            }

            // Get the diff for this file - handle different cases
            if ($commitRange === '--cached') {
                $command = \sprintf('git diff --cached -- %s', \escapeshellarg($file));
            } elseif (\str_contains($commitRange, ' -- ')) {
                // Handle specific file in commit format: abc1234 -- path/to/file.php
                // In this case, we show the specific file at that commit
                [$commit, $path] = \explode(' -- ', $commitRange, 2);
                // If the file matches the path, show it
                if ($path === $file || \str_starts_with($file, $path)) {
                    $command = \sprintf(
                        'git show %s:%s',
                        \escapeshellarg($commit),
                        \escapeshellarg($file),
                    );
                } else {
                    return ''; // Not matching the path filter
                }
            } else {
                $command = \sprintf(
                    'git diff %s -- %s',
                    \escapeshellarg($commitRange),
                    \escapeshellarg($file),
                );
            }

            $output = [];
            \exec($command, $output, $returnCode);

            if ($returnCode === 0) {
                $diff = \implode("\n", $output);
            }
        } finally {
            // Restore the original directory
            \chdir($currentDir);
        }

        return $diff;
    }

    /**
     * Apply filters to the file infos
     *
     * @param array<SplFileInfo> $fileInfos
     * @return array<SplFileInfo>
     */
    private function applyFilters(array $fileInfos, FilterableSourceInterface $source, string $tempDir): array
    {
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
     * @param string $commitRange Commit range for the header
     * @return string Text representation of the file tree
     */
    private function generateTreeView(array $files, string $commitRange): string
    {
        if (empty($files)) {
            return "No changes found in commit range: {$commitRange}\n";
        }

        $treeHeader = "Changes in commit range: {$commitRange}\n";
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
