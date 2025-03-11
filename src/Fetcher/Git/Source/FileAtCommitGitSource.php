<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Fetcher\Git\Source;

/**
 * Git source for specific files at a commit
 */
final class FileAtCommitGitSource extends AbstractGitSource
{
    /**
     * Check if this source supports the given commit reference
     */
    public function supports(string $commitReference): bool
    {
        // Support format: commit -- path
        return \str_contains($commitReference, ' -- ');
    }

    /**
     * Get a list of files at this commit that match the path filter
     *
     * @param string $repository Path to the Git repository
     * @param string $commitReference The commit reference with path filter
     * @return array<string> List of matching file paths
     */
    public function getChangedFiles(string $repository, string $commitReference): array
    {
        [$commit, $path] = \explode(' -- ', $commitReference, 2);

        $command = \sprintf(
            'git show --name-only %s -- %s',
            \escapeshellarg($commit),
            \escapeshellarg($path),
        );

        $output = $this->executeGitCommand($repository, $command);

        // The first line is the commit hash, so skip it
        if (!empty($output) && \preg_match('/^[0-9a-f]{40}$/', $output[0])) {
            \array_shift($output);
        }

        return \array_filter($output);
    }

    /**
     * Get the content of a specific file at the commit
     *
     * @param string $repository Path to the Git repository
     * @param string $commitReference The commit reference with path filter
     * @param string $file Path to the file
     * @return string File content at that commit
     */
    public function getFileDiff(string $repository, string $commitReference, string $file): string
    {
        [$commit, $path] = \explode(' -- ', $commitReference, 2);

        // Only process the file if it matches the path filter
        if ($path !== $file && !\str_starts_with($file, $path)) {
            return '';
        }

        $command = \sprintf(
            'git show %s:%s',
            \escapeshellarg($commit),
            \escapeshellarg($file),
        );

        return $this->executeGitCommandString($repository, $command);
    }

    /**
     * Format the reference for display in the tree view
     *
     * @param string $commitReference The commit reference with path filter
     * @return string Formatted reference for display
     */
    public function formatReferenceForDisplay(string $commitReference): string
    {
        [$commit, $path] = \explode(' -- ', $commitReference, 2);
        return "Files at commit {$commit} with path: {$path}";
    }
}