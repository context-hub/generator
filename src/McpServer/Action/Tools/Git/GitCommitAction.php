<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Git;

use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Lib\Git\Command;
use Butschster\ContextGenerator\Lib\Git\CommandsExecutorInterface;
use Butschster\ContextGenerator\Lib\Git\Exception\GitCommandException;
use Butschster\ContextGenerator\McpServer\Action\Tools\Git\Dto\GitCommitRequest;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'git-commit',
    description: 'Create a new commit with staged changes. Records changes to the repository with a commit message. Use git-add tool first to stage files, or use stageAll option to stage all tracked files automatically',
    title: 'Git Commit',
)]
#[InputSchema(class: GitCommitRequest::class)]
final readonly class GitCommitAction
{
    public function __construct(
        private LoggerInterface $logger,
        private CommandsExecutorInterface $commandsExecutor,
        private DirectoriesInterface $dirs,
    ) {}

    #[Post(path: '/tools/call/git-commit', name: 'tools.git.commit')]
    public function __invoke(GitCommitRequest $request): CallToolResult
    {
        $this->logger->info('Processing git-commit tool');

        $repository = (string) $this->dirs->getRootPath();

        // Check if we're in a valid git repository
        if (!$this->commandsExecutor->isValidRepository($repository)) {
            return new CallToolResult([
                new TextContent(
                    text: 'Error: Not a git repository (or any of the parent directories)',
                ),
            ], isError: true);
        }

        // Validate commit message
        if (empty(\trim($request->message))) {
            return new CallToolResult([
                new TextContent(
                    text: 'Error: Commit message cannot be empty',
                ),
            ], isError: true);
        }

        try {
            $commandParts = ['commit'];

            // Add commit message
            $commandParts[] = '--message';
            $commandParts[] = $request->message;

            // Stage all tracked files if requested
            if ($request->stageAll) {
                $commandParts[] = '--all';
            }

            // Check if there are changes to commit (unless allowEmpty is true)
            if (!$this->hasChangesToCommit($repository, $request->stageAll)) {
                return new CallToolResult([
                    new TextContent(
                        text: 'Error: No changes to commit. Use git-add to stage files or enable stageAll option',
                    ),
                ], isError: true);
            }

            $command = new Command($repository, $commandParts);
            $result = $this->commandsExecutor->executeString($command);

            return new CallToolResult([
                new TextContent(
                    text: $result,
                ),
            ]);
        } catch (GitCommandException $e) {
            $this->logger->error('Error executing git commit', [
                'repository' => $repository,
                'message' => $request->message,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return new CallToolResult([
                new TextContent(
                    text: 'Error: ' . $e->getMessage(),
                ),
            ], isError: true);
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error during git commit', [
                'repository' => $repository,
                'message' => $request->message,
                'error' => $e->getMessage(),
            ]);

            return new CallToolResult([
                new TextContent(
                    text: 'Error: ' . $e->getMessage(),
                ),
            ], isError: true);
        }
    }

    private function hasChangesToCommit(string $repository, bool $stageAll): bool
    {
        try {
            if ($stageAll) {
                // Check if there are any tracked files with changes
                $statusCommand = new Command($repository, ['status', '--porcelain']);
                $status = $this->commandsExecutor->executeString($statusCommand);
                return !empty(\trim($status));
            }
            // Check if there are staged changes
            $diffCommand = new Command($repository, ['diff', '--cached', '--name-only']);
            $stagedFiles = $this->commandsExecutor->executeString($diffCommand);
            return !empty(\trim($stagedFiles));
        } catch (\Throwable) {
            // If we can't check, assume there might be changes to avoid blocking
            return true;
        }
    }
}
