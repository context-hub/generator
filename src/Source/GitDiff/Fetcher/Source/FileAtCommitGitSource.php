<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff\Fetcher\Source;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;

/**
 * Git source for specific files at a commit
 */
#[LoggerPrefix(prefix: 'git.file_at_commit')]
final readonly class FileAtCommitGitSource extends AbstractGitSource
{
    public function supports(string $commitReference): bool
    {
        // Support format: commit -- path
        return \str_contains(haystack: $commitReference, needle: ' -- ');
    }

    public function getChangedFiles(string $repository, string $commitReference): array
    {
        [$commit, $path] = \explode(separator: ' -- ', string: $commitReference, limit: 2);

        $output = $this->executeGitCommand(
            repository: $repository,
            command: \sprintf('show --name-only %s -- %s', $commit, $path),
        );

        // The first line is the commit hash, so skip it
        if (!empty($output) && \preg_match(pattern: '/^[0-9a-f]{40}$/', subject: $output[0])) {
            \array_shift(array: $output);
        }

        return \array_filter(array: $output);
    }

    public function getFileDiff(string $repository, string $commitReference, string $file): string
    {
        [$commit, $path] = \explode(separator: ' -- ', string: $commitReference, limit: 2);

        // Only process the file if it matches the path filter
        if ($path !== $file && !\str_starts_with(haystack: $file, needle: $path)) {
            return '';
        }

        return $this->executeGitCommandString(
            repository: $repository,
            command: \sprintf('show %s:%s', $commit, $file),
        );
    }

    public function formatReferenceForDisplay(string $commitReference): string
    {
        [$commit, $path] = \explode(separator: ' -- ', string: $commitReference, limit: 2);

        return "Files at commit {$commit} with path: {$path}";
    }
}
