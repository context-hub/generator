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
        return \str_contains($commitReference, ' -- ');
    }

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

    public function getFileDiff(string $repository, string $commitReference, string $file): string
    {
        [$commit, $path] = \explode(' -- ', $commitReference, 2);

        // Only process the file if it matches the path filter
        if ($path !== $file && !\str_starts_with($file, $path)) {
            return '';
        }

        return $this->executeGitCommandString(
            repository: $repository,
            command: \sprintf(
                'git show %s:%s',
                \escapeshellarg($commit),
                \escapeshellarg($file),
            ),
        );
    }

    public function formatReferenceForDisplay(string $commitReference): string
    {
        [$commit, $path] = \explode(' -- ', $commitReference, 2);
        return "Files at commit {$commit} with path: {$path}";
    }
}
