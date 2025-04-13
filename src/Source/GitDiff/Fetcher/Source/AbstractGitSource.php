<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff\Fetcher\Source;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\Lib\Git\Command;
use Butschster\ContextGenerator\Lib\Git\CommandsExecutorInterface;
use Butschster\ContextGenerator\Lib\Git\Exception\GitCommandException;
use Butschster\ContextGenerator\Source\GitDiff\Fetcher\GitSourceInterface;
use Psr\Log\LoggerInterface;
use Spiral\Files\FilesInterface;
use Symfony\Component\Finder\SplFileInfo;

abstract readonly class AbstractGitSource implements GitSourceInterface
{
    public function __construct(
        protected CommandsExecutorInterface $commandsExecutor,
        private FilesInterface $files,
        protected ?LoggerInterface $logger = null,
    ) {}

    public function createFileInfos(string $repository, string $commitReference, string $tempDir): array
    {
        $changedFiles = $this->getChangedFiles($repository, $commitReference);

        if (empty($changedFiles)) {
            return [];
        }

        $this->files->ensureDirectory($tempDir, 0777);

        // Write each diff to a temporary file
        $fileInfos = [];

        foreach ($changedFiles as $file) {
            // Get the diff for this file
            $diff = $this->getFileDiff($repository, $commitReference, $file);

            if (empty($diff)) {
                continue;
            }

            // Create the temporary file
            $tempFile = FSPath::create($tempDir)->join($file);
            $tempDirname = (string) $tempFile->parent();

            $this->files->ensureDirectory($tempDirname, 0777);
            $this->files->write((string) $tempFile, $diff);

            // Create a file info object with additional metadata
            $fileInfos[] = new class((string) $tempFile, $file, $diff) extends SplFileInfo {
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
     * Execute a Git command in the repository directory and return the output as an array of lines
     *
     * @param string $repository Path to the Git repository
     * @param string $command Git command to execute
     * @return array<string> Command output lines
     */
    protected function executeGitCommand(string $repository, string $command): array
    {
        try {
            $result = $this->executeGitCommandString(repository: $repository, command: $command);
            return \array_map('trim', \array_filter(\explode(PHP_EOL, $result)));
        } catch (GitCommandException $e) {
            $this->logger?->warning('Git command failed, returning empty result', [
                'command' => $command,
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
            return $this->commandsExecutor->executeString(new Command(repository: $repository, command: $command));
        } catch (GitCommandException $e) {
            $this->logger?->warning('Git command failed, returning empty result', [
                'command' => $command,
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }
}
