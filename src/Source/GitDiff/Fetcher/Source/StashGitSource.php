<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff\Fetcher\Source;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;

/**
 * Git source for stash references
 */
#[LoggerPrefix(prefix: 'git.stash')]
final readonly class StashGitSource extends AbstractGitSource
{
    public function supports(string $commitReference): bool
    {
        // Support regular stash references like stash@{0}
        if (\preg_match('/^stash@\{\d+\}$/', $commitReference)) {
            return true;
        }

        // Support stash ranges like stash@{0}..stash@{2}
        if (\preg_match('/^stash@\{\d+\}\.\.stash@\{\d+\}$/', $commitReference)) {
            return true;
        }

        // Support stash with message search like stash@{/message}
        if (\preg_match('/^stash@\{\/(.*)\}$/', $commitReference)) {
            return true;
        }

        return false;
    }

    public function getChangedFiles(string $repository, string $commitReference): array
    {
        // Handle single stash reference: stash@{0}
        if (\preg_match('/^stash@\{\d+\}$/', $commitReference)) {
            return $this->executeGitCommand(
                repository: $repository,
                command: 'git stash show --name-only ' . $commitReference,
            );
        }

        // Handle stash range: stash@{0}..stash@{2}
        if (\preg_match('/^stash@\{\d+\}\.\.stash@\{\d+\}$/', $commitReference)) {
            [$startStash, $endStash] = \explode('..', $commitReference);

            // Extract stash indices
            \preg_match('/stash@\{(\d+)\}/', $startStash, $startMatches);
            \preg_match('/stash@\{(\d+)\}/', $endStash, $endMatches);

            $startIndex = (int) $startMatches[1];
            $endIndex = (int) $endMatches[1];

            if ($startIndex > $endIndex) {
                // Swap to ensure correct order (smaller to larger)
                [$startIndex, $endIndex] = [$endIndex, $startIndex];
            }

            // Get files from each stash in the range
            $allFiles = [];
            for ($i = $startIndex; $i <= $endIndex; $i++) {
                $stashOutput = $this->executeGitCommand(
                    repository: $repository,
                    command: 'git stash show --name-only stash@{' . $i . '}',
                );

                if (!empty($stashOutput)) {
                    $allFiles = \array_merge($allFiles, $stashOutput);
                }
            }

            return \array_unique($allFiles);
        }

        // Handle stash with message search: stash@{/message}
        if (\preg_match('/^stash@\{\/(.*)\}$/', $commitReference, $matches)) {
            // Extract message search pattern
            $searchPattern = $matches[1] ?? '';
            if (empty($searchPattern)) {
                return [];
            }

            // First, get the stash index that matches the message
            $listOutput = $this->executeGitCommand(
                repository: $repository,
                command: 'git stash list',
            );

            if (empty($listOutput)) {
                return [];
            }

            // Find matching stash
            $matchingStashIndex = null;
            foreach ($listOutput as $index => $stashEntry) {
                if (\stripos($stashEntry, $searchPattern) !== false) {
                    $matchingStashIndex = $index;
                    break;
                }
            }

            if ($matchingStashIndex === null) {
                return [];
            }

            return $this->executeGitCommand(
                repository: $repository,
                command: 'git stash show --name-only stash@{' . $matchingStashIndex . '}',
            );
        }

        return [];
    }

    public function getFileDiff(string $repository, string $commitReference, string $file): string
    {
        // Handle single stash reference: stash@{0}
        if (\preg_match('/^stash@\{\d+\}$/', $commitReference)) {
            // FIX: Use the correct syntax for showing a file in a stash
            // The stash index needs to be extracted without the stash@{} syntax
            \preg_match('/^stash@\{(\d+)\}$/', $commitReference, $matches);
            $stashIndex = $matches[1] ?? 0;

            // Build the command with the stash index
            return $this->executeGitCommandString(
                repository: $repository,
                command: 'git diff stash@{' . $stashIndex . '} -- ' . \escapeshellarg($file),
            );
        }

        // Handle stash range (just use the first stash in the range for showing diff)
        if (\preg_match('/^stash@\{\d+\}\.\.stash@\{\d+\}$/', $commitReference)) {
            [$startStash,] = \explode('..', $commitReference);
            // Extract the stash index from startStash
            \preg_match('/^stash@\{(\d+)\}$/', $startStash, $matches);
            $stashIndex = $matches[1] ?? 0;

            // Build the command
            return $this->executeGitCommandString(
                repository: $repository,
                command: 'git diff stash@{' . $stashIndex . '} -- ' . \escapeshellarg($file),
            );
        }

        // Handle stash with message search: stash@{/message}
        if (\preg_match('/^stash@\{\/(.*)\}$/', $commitReference, $matches)) {
            // Extract message search pattern
            $searchPattern = $matches[1] ?? '';
            if (empty($searchPattern)) {
                return '';
            }

            // First, get the stash index that matches the message
            $listOutput = $this->executeGitCommand(
                repository: $repository,
                command: 'git stash list',
            );

            if (empty($listOutput)) {
                return '';
            }

            // Find matching stash
            $matchingStashIndex = null;
            foreach ($listOutput as $index => $stashEntry) {
                if (\stripos($stashEntry, $searchPattern) !== false) {
                    $matchingStashIndex = $index;
                    break;
                }
            }

            if ($matchingStashIndex === null) {
                return '';
            }

            // Use the correct diff command format
            return $this->executeGitCommandString(
                repository: $repository,
                command: 'git diff stash@{' . $matchingStashIndex . '} -- ' . \escapeshellarg($file),
            );
        }

        return '';
    }

    public function formatReferenceForDisplay(string $commitReference): string
    {
        $humanReadable = [
            'stash@{0}' => 'latest stash',
            'stash@{1}' => 'second most recent stash',
            'stash@{2}' => 'third most recent stash',
            'stash@{0}..stash@{1}' => 'latest 2 stashes',
            'stash@{0}..stash@{2}' => 'latest 3 stashes',
            'stash@{0}..stash@{4}' => 'latest 5 stashes',
            'stash@{0}..stash@{100}' => 'all stashes',
        ];

        if (isset($humanReadable[$commitReference])) {
            return "Changes in " . $humanReadable[$commitReference];
        }

        return "Changes in stash: {$commitReference}";
    }
}
