<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Git;

use Butschster\ContextGenerator\Lib\Git\Exception\GitClientException;
use Butschster\ContextGenerator\Lib\Git\Exception\GitCommandException;

/**
 * Comprehensive interface for executing Git commands and operations.
 */
interface CommandsExecutorInterface
{
    /**
     * Execute a Git command and return the output as an array of lines.
     *
     * @param string $repository Path to the Git repository
     * @param string $command Git command to execute (without 'git' prefix)
     * @return array<string> Command output lines
     * @throws GitClientException If the command execution fails
     */
    public function execute(string $repository, string $command): array;

    /**
     * Execute a Git command and return the output as a string.
     *
     * @param string $repository Path to the Git repository
     * @param string $command Git command to execute (without 'git' prefix)
     * @return string Command output as a string
     * @throws GitClientException If the command execution fails
     */
    public function executeString(string $repository, string $command): string;

    /**
     * Execute a Git command using an array of command parts.
     *
     * @param array<string> $command Command parts to execute (without 'git' prefix)
     * @return string Command output as a string
     * @throws GitCommandException If the command execution fails
     */
    public function executeCommand(array $command): string;

    /**
     * Check if a directory is a valid Git repository.
     *
     * @param string $repository Path to the Git repository
     * @return bool True if the directory is a valid Git repository
     */
    public function isValidRepository(string $repository): bool;

    /**
     * Applies a git patch to a file.
     *
     * @param string $filePath Path to the file to patch
     * @param string $patchContent Content of the patch to apply
     * @return string Result message
     * @throws GitCommandException If the patch application fails
     */
    public function applyPatch(string $filePath, string $patchContent): string;
}
