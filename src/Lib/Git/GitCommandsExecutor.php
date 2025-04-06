<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Git;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Lib\Git\Exception\GitCommandException;
use Psr\Log\LoggerInterface;
use Spiral\Files\Exception\FilesException;
use Spiral\Files\FilesInterface;
use Symfony\Component\Process\Process;

/**
 * Executes git commands and handles their results.
 */
final readonly class GitCommandsExecutor
{
    public function __construct(
        private FilesInterface $files,
        private DirectoriesInterface $dirs,
        #[LoggerPrefix(prefix: 'git-commands-executor')]
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Applies a git patch to a file.
     */
    public function applyPatch(string $filePath, string $patchContent): string
    {
        $rootPath = $this->dirs->getRootPath();
        $file = $rootPath->join($filePath);

        // Ensure the file exists
        if (!$file->exists()) {
            throw new GitCommandException(\sprintf('File "%s" does not exist', $filePath));
        }

        // Create a temporary file for the patch
        try {
            $patchFile = $this->files->tempFilename();
        } catch (FilesException $e) {
            $this->logger?->error('Failed to create temporary file for patch', [
                'error' => $e->getMessage(),
            ]);

            throw new GitCommandException('Failed to create temporary file for patch', 0, $e);
        }

        try {
            // Write the patch content to a temporary file
            $this->files->write($patchFile, $patchContent, FilesInterface::READONLY);

            // Apply the patch using git apply command
            $process = new Process(
                ['git', 'apply', '--whitespace=nowarn', $patchFile],
                (string) $rootPath,
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
            $this->files->exists($filePath) && $this->files->delete($filePath);
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

        $process = new Process($command, (string) $this->dirs->getRootPath());
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
