<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Git;

use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Lib\Git\Command;
use Butschster\ContextGenerator\Lib\Git\CommandsExecutorInterface;
use Butschster\ContextGenerator\Lib\Git\Exception\GitCommandException;
use Butschster\ContextGenerator\McpServer\Action\Tools\Git\Dto\GitStatusFormat;
use Butschster\ContextGenerator\McpServer\Action\Tools\Git\Dto\GitStatusRequest;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'git-status',
    description: 'Show the working tree status - displays paths that have differences between the index and the working tree, between the index and HEAD, and paths that are untracked',
    title: 'Git Status',
)]
#[InputSchema(class: GitStatusRequest::class)]
final readonly class GitStatusAction
{
    public function __construct(
        private LoggerInterface $logger,
        private CommandsExecutorInterface $commandsExecutor,
        private DirectoriesInterface $dirs,
    ) {}

    #[Post(path: '/tools/call/git-status', name: 'tools.git.status')]
    public function __invoke(GitStatusRequest $request): CallToolResult
    {
        $this->logger->info('Processing git-status tool');

        $repository = (string) $this->dirs->getRootPath();

        // Check if we're in a valid git repository
        if (!$this->commandsExecutor->isValidRepository($repository)) {
            return new CallToolResult([
                new TextContent(
                    text: 'Error: Not a git repository (or any of the parent directories)',
                ),
            ], isError: true);
        }

        try {
            $commandParts = ['status'];

            // Add format options
            switch ($request->format) {
                case GitStatusFormat::Short:
                    $commandParts[] = '--short';
                    break;
                case GitStatusFormat::Porcelain:
                    $commandParts[] = '--porcelain';
                    break;
                case GitStatusFormat::Long:
                    // Default format, no additional flags needed
                    break;
            }

            // Handle untracked files
            if ($request->showUntracked) {
                $commandParts[] = '--untracked-files=all';
            } else {
                $commandParts[] = '--untracked-files=no';
            }

            $command = new Command($repository, $commandParts);
            $result = $this->commandsExecutor->executeString($command);

            // If result is empty, indicate clean working tree
            if (empty(\trim($result))) {
                $result = \sprintf(
                    'On branch %s nothing to commit, working tree clean',
                    $this->getCurrentBranch(
                        $repository,
                    ),
                );
            }

            return new CallToolResult([
                new TextContent(
                    text: $result,
                ),
            ]);
        } catch (GitCommandException $e) {
            $this->logger->error('Error executing git status', [
                'repository' => $repository,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return new CallToolResult([
                new TextContent(
                    text: 'Error: ' . $e->getMessage(),
                ),
            ], isError: true);
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error during git status', [
                'repository' => $repository,
                'error' => $e->getMessage(),
            ]);

            return new CallToolResult([
                new TextContent(
                    text: 'Error: ' . $e->getMessage(),
                ),
            ], isError: true);
        }
    }

    private function getCurrentBranch(string $repository): string
    {
        try {
            $command = new Command($repository, ['branch', '--show-current']);
            $branch = \trim($this->commandsExecutor->executeString($command));
            return !empty($branch) ? $branch : 'HEAD';
        } catch (\Throwable) {
            return 'HEAD';
        }
    }
}
