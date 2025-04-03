<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Git;

use Butschster\ContextGenerator\Lib\Git\Exception\GitCommandException;
use Symfony\Component\Process\Process;

/**
 * Executes git commands and handles their results.
 */
final readonly class GitCommandsExecutor
{
    public function __construct(
        private string $workingDirectory,
    ) {}

    /**
     * Applies a git patch to a file.
     */
    public function applyPatch(string $filePath, string $patchContent): string
    {
        // Ensure the file exists
        if (!\file_exists($this->workingDirectory . '/' . $filePath)) {
            throw new GitCommandException(\sprintf('File "%s" does not exist', $filePath));
        }

        // Create a temporary file for the patch
        $patchFile = \tempnam(\sys_get_temp_dir(), 'git_patch_');
        if ($patchFile === false) {
            throw new GitCommandException('Failed to create temporary file for patch');
        }

        try {
            // Write the patch content to a temporary file
            \file_put_contents($patchFile, $patchContent);

            // Apply the patch using git apply command
            $process = new Process(
                ['git', 'apply', '--whitespace=nowarn', $patchFile],
                $this->workingDirectory,
            );

            $process->run();

            // Check if the command was successful
            if (!$process->isSuccessful()) {
                throw new GitCommandException(
                    \sprintf('Failed to apply patch: %s', $process->getErrorOutput()),
                    $process->getExitCode(),
                );
            }

            return \sprintf('Successfully applied patch to %s', $filePath);
        } finally {
            // Clean up the temporary file
            if (\file_exists($patchFile)) {
                \unlink($patchFile);
            }
        }
    }

    /**
     * Executes a git command.
     * @throws GitCommandException If the command execution fails
     */
    public function execute(array $command): string
    {
        // Prepend 'git' to the command array
        \array_unshift($command, 'git');

        $process = new Process($command, $this->workingDirectory);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new GitCommandException(
                \sprintf('Git command failed: %s', $process->getErrorOutput()),
                $process->getExitCode(),
            );
        }

        return $process->getOutput();
    }

    /**
     * Checks if the given directory is a git repository.
     *
     * @return bool True if the directory is a git repository, false otherwise
     */
    public function isGitRepository(): bool
    {
        try {
            $this->execute(['rev-parse', '--is-inside-work-tree']);
            return true;
        } catch (GitCommandException) {
            return false;
        }
    }
}
