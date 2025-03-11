<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Fetcher\Git\Source;

/**
 * Git source for stash references
 */
final class StashGitSource extends AbstractGitSource
{
    /**
     * Check if this source supports the given commit reference
     */
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

    /**
     * Get a list of files changed in this stash
     *
     * @param string $repository Path to the Git repository
     * @param string $commitReference The stash reference
     * @return array<string> List of changed file paths
     */
    public function getChangedFiles(string $repository, string $commitReference): array
    {
        // Handle single stash reference: stash@{0}
        if (\preg_match('/^stash@\{\d+\}$/', $commitReference)) {
            $command = 'git stash show --name-only ' . $commitReference;
            return $this->executeGitCommand($repository, $command);
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
                $stashRef = "stash@{$i}";
                $stashCommand = 'git stash show --name-only ' . $stashRef;
                $stashOutput = $this->executeGitCommand($repository, $stashCommand);
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
            $listCommand = 'git stash list';
            $listOutput = $this->executeGitCommand($repository, $listCommand);
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

            $command = 'git stash show --name-only stash@{' . $matchingStashIndex . '}';
            return $this->executeGitCommand($repository, $command);
        }

        return [];
    }

    /**
     * Get the diff for a specific file in the stash
     *
     * @param string $repository Path to the Git repository
     * @param string $commitReference The stash reference
     * @param string $file Path to the file
     * @return string Diff content
     */
    public function getFileDiff(string $repository, string $commitReference, string $file): string
    {
        // Handle single stash reference: stash@{0}
        if (\preg_match('/^stash@\{\d+\}$/', $commitReference)) {
            // FIX: Use the correct syntax for showing a file in a stash
            // The stash index needs to be extracted without the stash@{} syntax
            \preg_match('/^stash@\{(\d+)\}$/', $commitReference, $matches);
            $stashIndex = $matches[1] ?? 0;

            // Build the command with the stash index
            $command = 'git diff stash@{' . $stashIndex . '} -- ' . \escapeshellarg($file);
            return $this->executeGitCommandString($repository, $command);
        }

        // Handle stash range (just use the first stash in the range for showing diff)
        if (\preg_match('/^stash@\{\d+\}\.\.stash@\{\d+\}$/', $commitReference)) {
            [$startStash,] = \explode('..', $commitReference);
            // Extract the stash index from startStash
            \preg_match('/^stash@\{(\d+)\}$/', $startStash, $matches);
            $stashIndex = $matches[1] ?? 0;

            // Build the command
            $command = 'git diff stash@{' . $stashIndex . '} -- ' . \escapeshellarg($file);
            return $this->executeGitCommandString($repository, $command);
        }

        // Handle stash with message search: stash@{/message}
        if (\preg_match('/^stash@\{\/(.*)\}$/', $commitReference, $matches)) {
            // Extract message search pattern
            $searchPattern = $matches[1] ?? '';
            if (empty($searchPattern)) {
                return '';
            }

            // First, get the stash index that matches the message
            $listCommand = 'git stash list';
            $listOutput = $this->executeGitCommand($repository, $listCommand);
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
            $command = 'git diff stash@{' . $matchingStashIndex . '} -- ' . \escapeshellarg($file);
            return $this->executeGitCommandString($repository, $command);
        }

        return '';
    }

    /**
     * Format the stash reference for display in the tree view
     *
     * @param string $commitReference The stash reference
     * @return string Formatted reference for display
     */
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
