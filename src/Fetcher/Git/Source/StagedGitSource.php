<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Fetcher\Git\Source;

/**
 * Git source for staged changes (in the index but not committed)
 */
final class StagedGitSource extends AbstractGitSource
{
    /**
     * Check if this source supports the given commit reference
     */
    public function supports(string $commitReference): bool
    {
        return $commitReference === '--cached' || $commitReference === 'staged';
    }

    /**
     * Get a list of files with staged changes
     *
     * @param string $repository Path to the Git repository
     * @param string $commitReference The commit reference (ignored)
     * @return array<string> List of changed file paths
     */
    public function getChangedFiles(string $repository, string $commitReference): array
    {
        $command = 'git diff --name-only --cached';
        return $this->executeGitCommand($repository, $command);
    }

    /**
     * Get the diff for a specific staged file
     *
     * @param string $repository Path to the Git repository
     * @param string $commitReference The commit reference (ignored)
     * @param string $file Path to the file
     * @return string Diff content
     */
    public function getFileDiff(string $repository, string $commitReference, string $file): string
    {
        $command = \sprintf('git diff --cached -- %s', \escapeshellarg($file));
        return $this->executeGitCommandString($repository, $command);
    }

    /**
     * Format the reference for display in the tree view
     *
     * @param string $commitReference The commit reference
     * @return string Formatted reference for display
     */
    public function formatReferenceForDisplay(string $commitReference): string
    {
        return "Changes staged for commit";
    }
}
