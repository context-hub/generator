<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff\Fetcher\Source;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;

/**
 * Git source for time-based ranges
 */
#[LoggerPrefix(prefix: 'git.time_range')]
final readonly class TimeRangeGitSource extends AbstractGitSource
{
    public function supports(string $commitReference): bool
    {
        // Match time-based ranges like HEAD@{1.week.ago}..HEAD
        if (\preg_match(pattern: '/HEAD@\{.*\}\.\.HEAD/', subject: $commitReference)) {
            return true;
        }

        // Match --since= format
        if (\str_contains(haystack: $commitReference, needle: '--since=')) {
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

        return \in_array(needle: $commitReference, haystack: $timeBasedPresets, strict: true);
    }

    public function getChangedFiles(string $repository, string $commitReference): array
    {
        if (\str_contains(haystack: $commitReference, needle: '--since=')) {
            $output = $this->executeGitCommand(
                repository: $repository,
                command: 'log ' . $commitReference . ' --name-only --pretty=format:""',
            );

            // Remove empty lines and duplicates
            return \array_unique(array: \array_filter(array: $output));
        }

        return $this->executeGitCommand(
            repository: $repository,
            command: \sprintf('diff --name-only %s', $commitReference),
        );
    }

    public function getFileDiff(string $repository, string $commitReference, string $file): string
    {
        if (\str_contains(haystack: $commitReference, needle: '--since=')) {
            return $this->executeGitCommandString(
                repository: $repository,
                command: \sprintf('log %s -p -- %s', $commitReference, $file),
            );
        }


        return $this->executeGitCommandString(
            repository: $repository,
            command: \sprintf('diff %s -- %s', $commitReference, $file),
        );
    }

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

        if (\str_contains(haystack: $commitReference, needle: '--since=')) {
            $since = \str_replace(search: '--since=', replace: '', subject: $commitReference);
            return "Changes since {$since}";
        }

        return "Changes in time range: {$commitReference}";
    }
}
