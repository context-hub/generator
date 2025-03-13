<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff\Fetcher\Source;

/**
 * Git source for time-based ranges
 */
final class TimeRangeGitSource extends AbstractGitSource
{
    /**
     * Check if this source supports the given commit reference
     */
    public function supports(string $commitReference): bool
    {
        // Match time-based ranges like HEAD@{1.week.ago}..HEAD
        if (\preg_match('/HEAD@\{.*\}\.\.HEAD/', $commitReference)) {
            return true;
        }

        // Match --since= format
        if (\str_contains($commitReference, '--since=')) {
            return true;
        }

        // Common time-based presets
        $timeBasedPresets = [
            'HEAD@{0:00:00}..HEAD',
            'HEAD@{24.hours.ago}..HEAD',
            'HEAD@{1.days.ago}..HEAD@{0.days.ago}',
            'HEAD@{1.week.ago}..HEAD',
            'HEAD@{2.weeks.ago}..HEAD',
            'HEAD@{1.month.ago}..HEAD',
            'HEAD@{3.months.ago}..HEAD',
            'HEAD@{1.year.ago}..HEAD',
        ];

        return \in_array($commitReference, $timeBasedPresets, true);
    }

    /**
     * Get a list of files changed in this time range
     *
     * @param string $repository Path to the Git repository
     * @param string $commitReference The time-based reference
     * @return array<string> List of changed file paths
     */
    public function getChangedFiles(string $repository, string $commitReference): array
    {
        if (\str_contains($commitReference, '--since=')) {
            $command = 'git log ' . $commitReference . ' --name-only --pretty=format:""';
            $output = $this->executeGitCommand($repository, $command);

            // Remove empty lines and duplicates
            return \array_unique(\array_filter($output));
        }

        $command = \sprintf('git diff --name-only %s', \escapeshellarg($commitReference));
        return $this->executeGitCommand($repository, $command);
    }

    /**
     * Get the diff for a specific file in the time range
     *
     * @param string $repository Path to the Git repository
     * @param string $commitReference The time-based reference
     * @param string $file Path to the file
     * @return string Diff content
     */
    public function getFileDiff(string $repository, string $commitReference, string $file): string
    {
        if (\str_contains($commitReference, '--since=')) {
            $command = \sprintf(
                'git log %s -p -- %s',
                \escapeshellarg($commitReference),
                \escapeshellarg($file),
            );

            return $this->executeGitCommandString($repository, $command);
        }

        $command = \sprintf(
            'git diff %s -- %s',
            \escapeshellarg($commitReference),
            \escapeshellarg($file),
        );

        return $this->executeGitCommandString($repository, $command);
    }

    /**
     * Format the time-based reference for display in the tree view
     *
     * @param string $commitReference The time-based reference
     * @return string Formatted reference for display
     */
    public function formatReferenceForDisplay(string $commitReference): string
    {
        $humanReadable = [
            'HEAD@{0:00:00}..HEAD' => 'today',
            'HEAD@{24.hours.ago}..HEAD' => 'last 24 hours',
            'HEAD@{1.days.ago}..HEAD@{0.days.ago}' => 'yesterday',
            'HEAD@{1.week.ago}..HEAD' => 'last week',
            'HEAD@{2.weeks.ago}..HEAD' => 'last 2 weeks',
            'HEAD@{1.month.ago}..HEAD' => 'last month',
            'HEAD@{3.months.ago}..HEAD' => 'last quarter',
            'HEAD@{1.year.ago}..HEAD' => 'last year',
        ];

        if (isset($humanReadable[$commitReference])) {
            return "Changes from " . $humanReadable[$commitReference];
        }

        if (\str_contains($commitReference, '--since=')) {
            $since = \str_replace('--since=', '', $commitReference);
            return "Changes since {$since}";
        }

        return "Changes in time range: {$commitReference}";
    }
}
