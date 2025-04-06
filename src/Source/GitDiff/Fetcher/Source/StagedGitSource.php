<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff\Fetcher\Source;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;

/**
 * Git source for staged changes (in the index but not committed)
 */
#[LoggerPrefix(prefix: 'git.staged')]
final readonly class StagedGitSource extends AbstractGitSource
{
    public function supports(string $commitReference): bool
    {
        return $commitReference === '--cached' || $commitReference === 'staged';
    }

    public function getChangedFiles(string $repository, string $commitReference): array
    {
        return $this->executeGitCommand(
            repository: $repository,
            command: 'diff --name-only --cached',
        );
    }

    public function getFileDiff(string $repository, string $commitReference, string $file): string
    {
        return $this->executeGitCommandString(
            repository: $repository,
            command: \sprintf('diff --cached -- %s', $file),
        );
    }

    public function formatReferenceForDisplay(string $commitReference): string
    {
        return "Changes staged for commit";
    }
}
