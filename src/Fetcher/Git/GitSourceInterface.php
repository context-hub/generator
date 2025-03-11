<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Fetcher\Git;

use Symfony\Component\Finder\SplFileInfo;

/**
 * Interface for Git source types (commit, stash, etc.)
 *
 * Each source handles a specific type of Git reference and knows how to:
 * 1. Check if it supports a given commit reference
 * 2. Get the list of changed files
 * 3. Get the diff for a specific file
 * 4. Format the reference for display in the tree view
 */
interface GitSourceInterface
{
    /**
     * Check if this source supports the given commit reference
     */
    public function supports(string $commitReference): bool;

    /**
     * Get a list of files changed in this source
     *
     * @param string $repository Path to the Git repository
     * @param string $commitReference The commit reference to check
     * @return array<string> List of changed file paths
     */
    public function getChangedFiles(string $repository, string $commitReference): array;

    /**
     * Get the diff content for a specific file
     *
     * @param string $repository Path to the Git repository
     * @param string $commitReference The commit reference
     * @param string $file Path to the file
     * @return string Diff content
     */
    public function getFileDiff(string $repository, string $commitReference, string $file): string;

    /**
     * Create temporary file info objects for the diffs
     *
     * @param string $repository Path to the Git repository
     * @param string $commitReference The commit reference
     * @param string $tempDir Temporary directory to write diffs to
     * @return array<SplFileInfo> List of file info objects
     */
    public function createFileInfos(string $repository, string $commitReference, string $tempDir): array;

    /**
     * Format the reference for display in the tree view
     *
     * @param string $commitReference The commit reference
     * @return string Formatted reference for display
     */
    public function formatReferenceForDisplay(string $commitReference): string;
}