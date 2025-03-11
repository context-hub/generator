<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Fetcher\Git\Source;

use Butschster\ContextGenerator\Fetcher\Git\GitSourceInterface;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Abstract base class for Git sources
 *
 * Provides common functionality for all Git source types
 */
abstract class AbstractGitSource implements GitSourceInterface
{
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
        $currentDir = \getcwd();
        $output = [];

        try {
            // Change to the repository directory
            \chdir($repository);

            // Execute the command
            \exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                return [];
            }
        } finally {
            // Restore the original directory
            \chdir($currentDir);
        }

        return $output;
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
        $output = $this->executeGitCommand($repository, $command);

        return \implode("\n", $output);
    }
}
