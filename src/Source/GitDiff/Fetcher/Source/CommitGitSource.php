<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff\Fetcher\Source;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;

/**
 * Git source for commit references
 */
#[LoggerPrefix(prefix: 'git.commit')]
final readonly class CommitGitSource extends AbstractGitSource
{
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

    public function getChangedFiles(string $repository, string $commitReference): array
    {
        return $this->executeGitCommand(
            repository: $repository,
            command: \sprintf('git diff --name-only %s', \escapeshellarg($commitReference)),
        );
    }

    public function getFileDiff(string $repository, string $commitReference, string $file): string
    {
        return $this->executeGitCommandString(
            repository: $repository,
            command: \sprintf(
                'git diff %s -- %s',
                \escapeshellarg($commitReference),
                \escapeshellarg($file),
            ),
        );
    }

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
