<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff\Fetcher\Source;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;

/**
 * Git source for staged changes (in the index but not committed)
 */
#[LoggerPrefix(prefix: 'git.staged')]
final class StagedGitSource extends AbstractGitSource
{
    public function supports(string $commitReference): bool
    {
        return $commitReference === '--cached' || $commitReference === 'staged';
    }

    public function getChangedFiles(string $repository, string $commitReference): array
    {
        $command = 'git diff --name-only --cached';
        return $this->executeGitCommand($repository, $command);
    }

    public function getFileDiff(string $repository, string $commitReference, string $file): string
    {
        $command = \sprintf('git diff --cached -- %s', \escapeshellarg($file));
        return $this->executeGitCommandString($repository, $command);
    }

    public function formatReferenceForDisplay(string $commitReference): string
    {
        return "Changes staged for commit";
    }
}
