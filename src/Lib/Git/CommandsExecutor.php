<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Git;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Lib\Git\Exception\GitClientException;
use Butschster\ContextGenerator\Lib\Git\Exception\GitCommandException;
use Psr\Log\LoggerInterface;
use Spiral\Files\Exception\FilesException;
use Spiral\Files\FilesInterface;
use Symfony\Component\Process\Process;

/**
 * Executes git commands and handles their results.
 */
final class CommandsExecutor implements CommandsExecutorInterface
{
    /**
     * Static cache of validated repositories
     * @var array<string, bool>
     */
    private static array $validatedRepositories = [];

    public function __construct(
        private readonly FilesInterface $files,
        private readonly DirectoriesInterface $dirs,
        #[LoggerPrefix(prefix: 'git-commands-executor')]
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * Execute a Git command and return the output as an array of lines.
     *
     * @inheritDoc
     */
    public function execute(string $repository, string $command): array
    {
        $repoPath = (string) $this->dirs->getRootPath()->join($repository);

        $this->validateRepository($repoPath);

        $currentDir = \getcwd();
        $output = [];
        $returnCode = 0;

        try {
            // Change to the repository directory
            \chdir($repoPath);

            // Add 'git' prefix to the command if not already present
            $fullCommand = $this->prepareCommand($command);

            $this->logger?->debug('Executing Git command', [
                'command' => $fullCommand,
                'repository' => $repoPath,
            ]);

            // Execute the command with error output capture
            \exec($fullCommand . ' 2>&1', $output, $returnCode);

            if ($returnCode !== 0) {
                $errorOutput = $output;
                $this->logger?->error('Git command failed', [
                    'command' => $fullCommand,
                    'exitCode' => $returnCode,
                    'errorOutput' => $errorOutput,
                ]);

                throw new GitClientException($fullCommand, $returnCode, $errorOutput);
            }

            $this->logger?->debug('Git command executed successfully', [
                'command' => $fullCommand,
                'outputLines' => \count($output),
            ]);

            return $output;
        } finally {
            // Restore the original directory
            if ($currentDir !== false) {
                \chdir($currentDir);
            }
        }
    }

    /**
     * Execute a Git command and return the output as a string.
     *
     * @inheritDoc
     */
    public function executeString(string $repository, string $command): string
    {
        $output = $this->execute($repository, $command);

        return \implode(PHP_EOL, $output);
    }

    /**
     * Execute a Git command using an array of command parts.
     *
     * @inheritDoc
     */
    public function executeCommand(array $command): string
    {
        // Prepend 'git' to the command array
        $gitCommand = ['git'];
        $fullCommand = \array_merge($gitCommand, $command);

        $process = new Process($fullCommand, (string) $this->dirs->getRootPath());
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
     * Check if a directory is a valid Git repository.
     *
     * @inheritDoc
     */
    public function isValidRepository(string $repository): bool
    {
        // Return cached result if available
        if (isset(self::$validatedRepositories[$repository])) {
            $this->logger?->debug('Using cached repository validation result', [
                'repository' => $repository,
                'isValid' => self::$validatedRepositories[$repository],
            ]);
            return self::$validatedRepositories[$repository];
        }

        if (!\is_dir($repository)) {
            $this->logger?->debug('Repository directory does not exist', [
                'repository' => $repository,
            ]);
            self::$validatedRepositories[$repository] = false;
            return false;
        }

        $currentDir = \getcwd();

        try {
            \chdir($repository);

            // Check if this is a git repository by running a simple git command
            /** @var array<string> $output */
            \exec('git rev-parse --is-inside-work-tree 2>/dev/null', $output, $returnCode);

            $isValid = ($returnCode === 0 && isset($output[0]) && \trim($output[0]) === 'true');

            $this->logger?->debug('Repository validation result', [
                'repository' => $repository,
                'isValid' => $isValid,
            ]);

            // Cache the result in static array
            self::$validatedRepositories[$repository] = $isValid;

            return $isValid;
        } catch (\Exception $e) {
            $this->logger?->error('Error validating repository', [
                'repository' => $repository,
                'error' => $e->getMessage(),
            ]);
            self::$validatedRepositories[$repository] = false;
            return false;
        } finally {
            // Restore the original directory
            if ($currentDir !== false) {
                \chdir($currentDir);
            }
        }
    }

    /**
     * Applies a git patch to a file.
     *
     * @inheritDoc
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
            if ($this->files->exists($patchFile)) {
                $this->files->delete($patchFile);
            }
        }
    }

    /**
     * Checks if the given directory is a git repository.
     *
     * @return bool True if the directory is a git repository, false otherwise
     * @internal This method is kept for backward compatibility
     */
    public function isGitRepository(): bool
    {
        try {
            $this->executeCommand(['rev-parse', '--is-inside-work-tree']);
            return true;
        } catch (GitCommandException) {
            return false;
        }
    }

    /**
     * Validate that the repository directory exists and is a valid Git repository
     * Uses cached results when available
     *
     * @param string $repository Path to the Git repository
     * @throws \InvalidArgumentException If the repository is invalid
     */
    private function validateRepository(string $repository): void
    {
        if (!$this->isValidRepository($repository)) {
            $this->logger?->error('Not a valid Git repository', [
                'repository' => $repository,
            ]);
            throw new \InvalidArgumentException(\sprintf('"%s" is not a valid Git repository', $repository));
        }
    }

    /**
     * Prepare a Git command by adding 'git' prefix if needed
     */
    private function prepareCommand(string $command): string
    {
        $command = \trim($command);

        // If the command already starts with 'git', use it as is
        if (\str_starts_with($command, 'git ')) {
            return $command;
        }

        return 'git ' . $command;
    }
}
