<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem;

use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Lib\Git\Exception\GitCommandException;
use Butschster\ContextGenerator\Lib\Git\GitCommandsExecutor;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'file-apply-patch',
    description: 'Apply a git patch to a file within the project',
)]
#[InputSchema(
    name: 'path',
    type: 'string',
    description: 'Path to the file to patch, relative to project root',
    required: true,
)]
#[InputSchema(
    name: 'patch',
    type: 'string',
    description: 'Content of the git patch to apply. It must be a valid git diff format.',
    required: true,
)]
final readonly class FileApplyPatchAction
{
    public function __construct(
        private LoggerInterface $logger,
        private DirectoriesInterface $dirs,
    ) {}

    #[Post(path: '/tools/call/file-apply-patch', name: 'tools.file-apply-patch')]
    public function __invoke(ServerRequestInterface $request): CallToolResult
    {
        $this->logger->info('Processing file-apply-patch tool');

        // Get params from the parsed body for POST requests
        $parsedBody = $request->getParsedBody();
        $path = $parsedBody['path'] ?? '';
        $patch = $parsedBody['patch'] ?? '';

        if (empty($path)) {
            return new CallToolResult([
                new TextContent(
                    text: 'Error: Missing path parameter',
                ),
            ], isError: true);
        }

        if (empty($patch)) {
            return new CallToolResult([
                new TextContent(
                    text: 'Error: Missing patch parameter',
                ),
            ], isError: true);
        }

        try {
            $projectRoot = (string) $this->dirs->getRootPath();

            // Initialize the git commands executor
            $gitExecutor = new GitCommandsExecutor($projectRoot);

            // Check if the directory is a git repository
            if (!$gitExecutor->isGitRepository()) {
                return new CallToolResult([
                    new TextContent(
                        text: 'Error: The project directory is not a git repository',
                    ),
                ], isError: true);
            }

            // Apply the patch
            $result = $gitExecutor->applyPatch($path, $patch);

            return new CallToolResult([
                new TextContent(
                    text: $result,
                ),
            ]);
        } catch (GitCommandException $e) {
            $this->logger->error('Error applying git patch', [
                'path' => $path,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return new CallToolResult([
                new TextContent(
                    text: 'Error: ' . $e->getMessage(),
                ),
            ], isError: true);
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error applying git patch', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return new CallToolResult([
                new TextContent(
                    text: 'Error: ' . $e->getMessage(),
                ),
            ], isError: true);
        }
    }
}
