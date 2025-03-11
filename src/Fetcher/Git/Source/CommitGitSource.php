<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Fetcher\Git\Source;

/**
 * Git source for commit references
 */
final class CommitGitSource extends AbstractGitSource
{
    /**
     * Check if this source supports the given commit reference
     */
    public function supports(string $commitReference): bool
    {
        // Basic commit range like commit1..commit2
        if (\preg_match('/^[^\.]+\.\.[^\.]+$/', $commitReference)) {
            return true;
        }

        // Single commit hash like abc1234
        if (\preg_match('/^[0-9a-f]{7,40}$/', $commitReference)) {
            return true;
        }

        // Commonly used reference formats
        $commonFormats = [
            'HEAD~1..HEAD',
            'HEAD~2..HEAD',
            'HEAD~3..HEAD',
            'HEAD~5..HEAD',
            'HEAD~10..HEAD',
            'main..HEAD',
            'master..HEAD',
            'develop..HEAD',
        ];

        if (\in_array($commitReference, $commonFormats, true)) {
            return true;
        }

        return false;
    }

    /**
     * Get a list of files changed in this commit range
     *
     * @param string $repository Path to the Git repository
     * @param string $commitReference The commit reference
     * @return array<string> List of changed file paths
     */
    public function getChangedFiles(string $repository, string $commitReference): array
    {
        $command = \sprintf('git diff --name-only %s', \escapeshellarg($commitReference));
        return $this->executeGitCommand($repository, $command);
    }

    /**
     * Get the diff for a specific file in the commit range
     *
     * @param string $repository Path to the Git repository
     * @param string $commitReference The commit reference
     * @param string $file Path to the file
     * @return string Diff content
     */
    public function getFileDiff(string $repository, string $commitReference, string $file): string
    {
        $command = \sprintf(
            'git diff %s -- %s',
            \escapeshellarg($commitReference),
            \escapeshellarg($file),
        );

        return $this->executeGitCommandString($repository, $command);
    }

    /**
     * Format the commit reference for display in the tree view
     *
     * @param string $commitReference The commit reference
     * @return string Formatted reference for display
     */
    public function formatReferenceForDisplay(string $commitReference): string
    {
        $humanReadable = [
            'HEAD~1..HEAD' => 'last commit',
            'HEAD~5..HEAD' => 'last 5 commits',
            'HEAD~10..HEAD' => 'last 10 commits',
            'main..HEAD' => 'changes since diverging from main',
            'master..HEAD' => 'changes since diverging from master',
            'develop..HEAD' => 'changes since diverging from develop',
        ];

        if (isset($humanReadable[$commitReference])) {
            return "Changes in " . $humanReadable[$commitReference];
        }

        return "Changes in commit range: {$commitReference}";
    }
}