<?php

declare(strict_types=1);

namespace Tests\Source\GitDiff\Fetcher\Source;

use Butschster\ContextGenerator\Lib\Git\Command;
use Butschster\ContextGenerator\Lib\Git\CommandsExecutorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Base test case for Git source tests
 * Provides common functionality for setting up git mocks for testing
 */
abstract class GitSourceTestCase extends TestCase
{
    protected string $repoDir = '/mocked/repo/path';
    protected MockObject&CommandsExecutorInterface $commandExecutorMock;
    protected LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock for GitClientInterface
        $this->commandExecutorMock = $this->createMock(CommandsExecutorInterface::class);

        // Create a logger instance
        $this->logger = new NullLogger();

        // Set default mock behavior for isValidRepository
        $this->commandExecutorMock
            ->method('isValidRepository')
            ->with($this->repoDir)
            ->willReturn(true);
    }

    /**
     * Configure mock for getting changed files
     *
     * @param string $command The git command to match
     * @param array<string> $files The files to return
     */
    protected function mockChangedFiles(string $command, array $files): void
    {
        $this->commandExecutorMock
            ->expects($this->atLeastOnce())
            ->method('executeString')
            ->with(new Command($this->repoDir, $command))
            ->willReturn(\implode("\n", $files));
    }

    /**
     * Configure mock for getting file diff
     *
     * @param string $command The git command to match
     * @param string $diff The diff content to return
     */
    protected function mockFileDiff(string $command, string $diff): void
    {
        $this->commandExecutorMock
            ->expects($this->atLeastOnce())
            ->method('executeString')
            ->with(new Command($this->repoDir, $command))
            ->willReturn($diff);
    }

    /**
     * Mock commit hash generation
     *
     * @param string $hash The commit hash to return
     */
    protected function mockCommitHash(string $hash): string
    {
        $this->commandExecutorMock
            ->method('executeString')
            ->with(new Command($this->repoDir, 'git rev-parse HEAD'))
            ->willReturn($hash);

        return $hash;
    }

    /**
     * Mock a git command execution with custom parameters and result
     *
     * @param string $repository The repository path to match
     * @param string $command The command to match
     * @param array<string> $result The result to return
     */
    protected function mockGitCommand(string $repository, string $command, array $result): void
    {
        $this->commandExecutorMock
            ->method('executeString')
            ->with(new Command($this->repoDir, $command))
            ->willReturn($result);
    }
}
