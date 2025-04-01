<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff\Git;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Default implementation of the GitClientInterface
 *
 * Provides methods to execute Git commands in a repository context.
 * Uses static caching to avoid checking repositories on every request.
 */
final class GitClient implements GitClientInterface
{
    /**
     * Static cache of validated repositories
     * @var array<string, bool>
     */
    private static array $validatedRepositories = [];

    /**
     * @param LoggerInterface $logger PSR Logger instance
     */
    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * Execute a Git command and return the output as an array of lines
     *
     * @param string $repository Path to the Git repository
     * @param string $command Git command to execute (without 'git' prefix)
     * @return array<string> Command output lines
     * @throws GitCommandException If the command execution fails
     */
    public function execute(string $repository, string $command): array
    {
        $this->validateRepository($repository);

        $currentDir = \getcwd();
        $output = [];
        $returnCode = 0;

        try {
            // Change to the repository directory
            \chdir($repository);

            // Add 'git' prefix to the command if not already present
            $fullCommand = $this->prepareCommand($command);

            $this->logger->debug('Executing Git command', [
                'command' => $fullCommand,
                'repository' => $repository,
            ]);

            // Execute the command with error output capture
            \exec($fullCommand . ' 2>&1', $output, $returnCode);

            if ($returnCode !== 0) {
                $errorOutput = $output;
                $this->logger->error('Git command failed', [
                    'command' => $fullCommand,
                    'exitCode' => $returnCode,
                    'errorOutput' => $errorOutput,
                ]);

                throw new GitCommandException($fullCommand, $returnCode, $errorOutput);
            }

            $this->logger->debug('Git command executed successfully', [
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
     * Execute a Git command and return the output as a string
     *
     * @param string $repository Path to the Git repository
     * @param string $command Git command to execute (without 'git' prefix)
     * @return string Command output as a string
     * @throws GitCommandException If the command execution fails
     */
    public function executeString(string $repository, string $command): string
    {
        $output = $this->execute($repository, $command);
        return \implode(PHP_EOL, $output);
    }

    /**
     * Check if a directory is a valid Git repository
     * Uses static cache for already validated repositories
     *
     * @param string $repository Path to the Git repository
     * @return bool True if the directory is a valid Git repository
     */
    public function isValidRepository(string $repository): bool
    {
        // Return cached result if available
        if (isset(self::$validatedRepositories[$repository])) {
            $this->logger->debug('Using cached repository validation result', [
                'repository' => $repository,
                'isValid' => self::$validatedRepositories[$repository],
            ]);
            return self::$validatedRepositories[$repository];
        }

        if (!\is_dir($repository)) {
            $this->logger->debug('Repository directory does not exist', [
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

            $this->logger->debug('Repository validation result', [
                'repository' => $repository,
                'isValid' => $isValid,
            ]);

            // Cache the result in static array
            self::$validatedRepositories[$repository] = $isValid;

            return $isValid;
        } catch (\Exception $e) {
            $this->logger->error('Error validating repository', [
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
     * Validate that the repository directory exists and is a valid Git repository
     * Uses cached results when available
     *
     * @param string $repository Path to the Git repository
     * @throws \InvalidArgumentException If the repository is invalid
     */
    private function validateRepository(string $repository): void
    {
        if (!\is_dir($repository)) {
            $this->logger->error('Repository directory does not exist', [
                'repository' => $repository,
            ]);
            throw new \InvalidArgumentException(\sprintf('Git repository "%s" does not exist', $repository));
        }

        if (!$this->isValidRepository($repository)) {
            $this->logger->error('Not a valid Git repository', [
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
