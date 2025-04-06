<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff\Fetcher\Source;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;

/**
 * Git source for unstaged changes (not yet added to the index)
 */
#[LoggerPrefix(prefix: 'git.unstaged')]
final class UnstagedGitSource extends AbstractGitSource
{
    public function supports(string $commitReference): bool
    {
        return $commitReference === '' || $commitReference === 'unstaged';
    }

    public function getChangedFiles(string $repository, string $commitReference): array
    {
        $command = 'git diff --name-only';
        return $this->executeGitCommand($repository, $command);
    }

    public function getFileDiff(string $repository, string $commitReference, string $file): string
    {
        $command = \sprintf('git diff -- %s', \escapeshellarg($file));
        return $this->executeGitCommandString($repository, $command);
    }

    public function formatReferenceForDisplay(string $commitReference): string
    {
        return "Unstaged changes";
    }
}
