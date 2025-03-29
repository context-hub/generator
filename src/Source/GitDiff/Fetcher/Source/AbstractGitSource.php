<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff\Fetcher\Source;

use Butschster\ContextGenerator\Source\GitDiff\Fetcher\GitSourceInterface;
use Butschster\ContextGenerator\Source\GitDiff\Git\GitClientInterface;
use Butschster\ContextGenerator\Source\GitDiff\Git\GitClient;
use Butschster\ContextGenerator\Source\GitDiff\Git\GitCommandException;
use Symfony\Component\Finder\SplFileInfo;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Abstract base class for Git sources
 *
 * Provides common functionality for all Git source types
 */
abstract class AbstractGitSource implements GitSourceInterface
{
    /**
     * @param GitClientInterface $gitClient Git client for executing commands
     * @param LoggerInterface $logger PSR Logger instance
     */
    public function __construct(
        protected readonly GitClientInterface $gitClient = new GitClient(),
        protected readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * Create file info objects for the diffs
     *
     * @param string $repository Path to the Git repository
     * @param string $commitReference The commit reference
     * @param string $tempDir Temporary directory to write diffs to
     * @return array<SplFileInfo> List of file info objects
     */
    public function createFileInfos(string $repository, string $commitReference, string $tempDir): array
    {
        $changedFiles = $this->getChangedFiles($repository, $commitReference);

        if (empty($changedFiles)) {
            return [];
        }

        // Create directory if it doesn't exist
        if (!\is_dir($tempDir)) {
            \mkdir($tempDir, 0777, true);
        }

        // Write each diff to a temporary file
        $fileInfos = [];

        foreach ($changedFiles as $file) {
            // Get the diff for this file
            $diff = $this->getFileDiff($repository, $commitReference, $file);

            if (empty($diff)) {
                continue;
            }

            // Create the temporary file
            $tempFile = $tempDir . '/' . $file;
            $tempDirname = \dirname($tempFile);

            if (!\is_dir($tempDirname)) {
                \mkdir($tempDirname, 0777, true);
            }

            \file_put_contents($tempFile, $diff);

            // Create a file info object with additional metadata
            $fileInfos[] = new class($tempFile, $file, $diff) extends SplFileInfo {
                private readonly string $originalPath;

                public function __construct(
                    string $tempFile,
                    string $originalPath,
                    private readonly string $diffContent,
                ) {
                    parent::__construct($tempFile, \dirname($originalPath), $originalPath);
                    $this->originalPath = $originalPath;
                }

                public function getOriginalPath(): string
                {
                    return $this->originalPath;
                }

                public function getDiffContent(): string
                {
                    return $this->diffContent;
                }

                public function getContents(): string
                {
                    return $this->diffContent;
                }
            };
        }

        return $fileInfos;
    }

    /**
     * Execute a Git command in the repository directory
     *
     * @param string $repository Path to the Git repository
     * @param string $command Git command to execute
     * @return array<string> Command output lines
     */
    protected function executeGitCommand(string $repository, string $command): array
    {
        try {
            return $this->gitClient->execute($repository, $command);
        } catch (GitCommandException $e) {
            $this->logger->warning('Git command failed, returning empty result', [
                'command' => $e->getCommand(),
                'exitCode' => $e->getExitCode(),
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Execute a Git command and get the output as a string
     *
     * @param string $repository Path to the Git repository
     * @param string $command Git command to execute
     * @return string Command output as a string
     */
    protected function executeGitCommandString(string $repository, string $command): string
    {
        try {
            return $this->gitClient->executeString($repository, $command);
        } catch (GitCommandException $e) {
            $this->logger->warning('Git command failed, returning empty result', [
                'command' => $e->getCommand(),
                'exitCode' => $e->getExitCode(),
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }
}
