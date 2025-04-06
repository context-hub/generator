<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Git;

use Butschster\ContextGenerator\Lib\Git\Exception\GitCommandException;

/**
 * Comprehensive interface for executing Git commands and operations.
 */
interface CommandsExecutorInterface
{
    /**
     * Execute a Git command and return the output as a string.
     *
     * @param Command $command Git command to execute
     * @return string Command output as a string
     * @throws GitCommandException If the command execution fails
     */
    public function executeString(Command $command): string;

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
