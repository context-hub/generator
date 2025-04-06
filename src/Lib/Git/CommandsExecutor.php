<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Git;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Lib\Git\Exception\GitCommandException;
use Psr\Log\LoggerInterface;
use Spiral\Files\Exception\FilesException;
use Spiral\Files\FilesInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

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

    public function executeString(Command $command): string
    {
        $repository = $command->repository;
        $repositoryPath = $this->resolvePath($repository);

        if (!$this->isValidRepository($repositoryPath)) {
            $this->logger?->error('Not a valid Git repository', [
                'repository' => $repositoryPath,
            ]);

            throw new \InvalidArgumentException(\sprintf('"%s" is not a valid Git repository', $repositoryPath));
        }

        $commandParts = ['git', ...$command->getCommandParts()];

        $this->logger?->debug('Executing Git command', [
            'command' => \implode(' ', $commandParts),
            'repository' => $repositoryPath,
        ]);

        try {
            $process = new Process($commandParts, $repositoryPath);
            $process->run();

            if (!$process->isSuccessful()) {
                $this->logger?->error('Git command failed', [
                    'command' => \implode(' ', $commandParts),
                    'exitCode' => $process->getExitCode(),
                    'errorOutput' => $process->getErrorOutput(),
                ]);

                throw new GitCommandException(
                    \sprintf(
                        'Git command "%s" failed with exit code %d: %s',
                        \implode(' ', $commandParts),
                        $process->getExitCode(),
                        $process->getErrorOutput(),
                    ),
                    $process->getExitCode(),
                );
            }

            $this->logger?->debug('Git command executed successfully', [
                'command' => \implode(' ', $commandParts),
                'outputLength' => \strlen($process->getOutput()),
            ]);

            return $process->getOutput();
        } catch (ProcessFailedException $e) {
            $this->logger?->error('Git command process failed', [
                'command' => \implode(' ', $commandParts),
                'error' => $e->getMessage(),
            ]);

            throw new GitCommandException(
                \sprintf('Git command process failed: %s', $e->getMessage()),
                $e->getCode(),
                $e,
            );
        }
    }

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

        $repositoryPath = $this->resolvePath($repository);

        if (!\is_dir($repositoryPath)) {
            $this->logger?->debug('Repository directory does not exist', [
                'repository' => $repository,
            ]);
            self::$validatedRepositories[$repository] = false;
            return false;
        }

        try {
            $process = new Process(
                ['git', 'rev-parse', '--is-inside-work-tree'],
                $repositoryPath,
            );

            $process->run();

            $isValid = $process->isSuccessful() && \trim($process->getOutput()) === 'true';

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
        }
    }

    public function applyPatch(string $filePath, string $patchContent): string
    {
        $rootPath = $this->dirs->getRootPath();

        if (!$this->isValidRepository((string) $rootPath)) {
            $this->logger?->error('Not a valid Git repository', [
                'repository' => (string) $rootPath,
            ]);

            throw new \InvalidArgumentException(\sprintf('"%s" is not a valid Git repository', $rootPath));
        }

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
            $this->files->delete($patchFile);
        }
    }

    /**
     * Resolve repository path relative to the root path.
     */
    private function resolvePath(string $repository): string
    {
        return (string) $this->dirs->getRootPath()->join($repository);
    }
}
