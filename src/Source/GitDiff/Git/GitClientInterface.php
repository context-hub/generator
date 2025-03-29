<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff\Git;

/**
 * Interface for Git operations
 *
 * Provides a clean abstraction layer for interacting with Git repositories.
 */
interface GitClientInterface
{
    /**
     * Execute a Git command and return the output as an array of lines
     *
     * @param string $repository Path to the Git repository
     * @param string $command Git command to execute (without 'git' prefix)
     * @return array<string> Command output lines
     * @throws GitCommandException If the command execution fails
     */
    public function execute(string $repository, string $command): array;

    /**
     * Execute a Git command and return the output as a string
     *
     * @param string $repository Path to the Git repository
     * @param string $command Git command to execute (without 'git' prefix)
     * @return string Command output as a string
     * @throws GitCommandException If the command execution fails
     */
    public function executeString(string $repository, string $command): string;

    /**
     * Check if a directory is a valid Git repository
     *
     * @param string $repository Path to the Git repository
     * @return bool True if the directory is a valid Git repository
     */
    public function isValidRepository(string $repository): bool;
}
