<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Git;

use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Lib\Git\Command;
use Butschster\ContextGenerator\Lib\Git\CommandsExecutorInterface;
use Butschster\ContextGenerator\Lib\Git\Exception\GitCommandException;
use Butschster\ContextGenerator\McpServer\Action\Tools\Git\Dto\GitAddRequest;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use PhpMcp\Schema\Result\CallToolResult;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'git-add',
    description: 'Add files to the staging area for the next commit. Stages changes in the working directory to be included in the next commit',
    title: 'Git Add',
)]
#[InputSchema(class: GitAddRequest::class)]
final readonly class GitAddAction
{
    public function __construct(
        private LoggerInterface $logger,
        private CommandsExecutorInterface $commandsExecutor,
        private DirectoriesInterface $dirs,
    ) {}

    #[Post(path: '/tools/call/git-add', name: 'tools.git.add')]
    public function __invoke(GitAddRequest $request): CallToolResult
    {
        $this->logger->info('Processing git-add tool');

        $repository = (string) $this->dirs->getRootPath();

        // Check if we're in a valid git repository
        if (!$this->commandsExecutor->isValidRepository($repository)) {
            return ToolResult::error('Not a git repository (or any of the parent directories)');
        }

        // Validate that paths are provided
        if (empty($request->paths)) {
            return ToolResult::error('No paths specified for staging');
        }

        try {
            $commandParts = ['add'];

            // Handle different add modes
            if ($request->all) {
                $commandParts[] = '--all';
            }

            // Add verbose output to show what files are being staged
            $commandParts[] = '--verbose';

            // Add the specified paths
            $commandParts = \array_merge($commandParts, $request->paths);

            $command = new Command($repository, $commandParts);
            $result = $this->commandsExecutor->executeString($command);

            // If no output, provide feedback about what was staged
            if (empty(\trim($result))) {
                $stagedInfo = $this->getStagedFilesInfo($repository, $request->paths);
                $result = $stagedInfo ?: 'Files staged successfully';
            }

            return ToolResult::text($result);
        } catch (GitCommandException $e) {
            $this->logger->error('Error executing git add', [
                'repository' => $repository,
                'paths' => $request->paths,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return ToolResult::error($e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error during git add', [
                'repository' => $repository,
                'paths' => $request->paths,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error($e->getMessage());
        }
    }

    private function getStagedFilesInfo(string $repository, array $paths): ?string
    {
        try {
            // Get list of staged files
            $command = new Command($repository, ['diff', '--cached', '--name-status']);
            $stagedFiles = $this->commandsExecutor->executeString($command);

            if (!empty(\trim($stagedFiles))) {
                $lines = \explode("\n", \trim($stagedFiles));
                $fileCount = \count($lines);
                $pathsDescription = \count($paths) === 1 && $paths[0] === '.' ? 'all files' : \implode(', ', $paths);

                return \sprintf(
                    "Staged %d file(s) from %s:\n%s",
                    $fileCount,
                    $pathsDescription,
                    $stagedFiles,
                );
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }
}
