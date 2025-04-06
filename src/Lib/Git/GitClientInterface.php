<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Git;

use Butschster\ContextGenerator\Lib\Git\Exception\GitClientException;

/**
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
     * @throws GitClientException If the command execution fails
     */
    public function execute(string $repository, string $command): array;

    /**
     * Execute a Git command and return the output as a string
     *
     * @param string $repository Path to the Git repository
     * @param string $command Git command to execute (without 'git' prefix)
     * @return string Command output as a string
     * @throws GitClientException If the command execution fails
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
