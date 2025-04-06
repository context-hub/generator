<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff\Fetcher\Source;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;

/**
 * Git source for unstaged changes (not yet added to the index)
 */
#[LoggerPrefix(prefix: 'git.unstaged')]
final readonly class UnstagedGitSource extends AbstractGitSource
{
    public function supports(string $commitReference): bool
    {
        return $commitReference === '' || $commitReference === 'unstaged';
    }

    public function getChangedFiles(string $repository, string $commitReference): array
    {
        return $this->executeGitCommand(
            repository: $repository,
            command: 'diff --name-only',
        );
    }

    public function getFileDiff(string $repository, string $commitReference, string $file): string
    {
        return $this->executeGitCommandString(
            repository: $repository,
            command: \sprintf('diff -- %s', $file),
        );
    }

    public function formatReferenceForDisplay(string $commitReference): string
    {
        return "Unstaged changes";
    }
}
