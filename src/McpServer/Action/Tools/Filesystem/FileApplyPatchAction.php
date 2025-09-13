<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem;

use Butschster\ContextGenerator\Lib\Git\CommandsExecutorInterface;
use Butschster\ContextGenerator\Lib\Git\Exception\GitCommandException;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\Dto\FileApplyPatchRequest;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'file-apply-patch',
    description: 'Apply a git patch to a file within the project',
)]
#[InputSchema(class: FileApplyPatchRequest::class)]
final readonly class FileApplyPatchAction
{
    public function __construct(
        private LoggerInterface $logger,
        private CommandsExecutorInterface $commandsExecutor,
    ) {}

    #[Post(path: '/tools/call/file-apply-patch', name: 'tools.file-apply-patch')]
    public function __invoke(FileApplyPatchRequest $request): CallToolResult
    {
        $this->logger->info('Processing file-apply-patch tool');

        // Get params from the parsed body for POST requests
        $path = $request->path;
        $patch = $request->patch;

        // Validate patch format
        if (!\str_starts_with($patch, 'diff --git a/')) {
            return new CallToolResult([
                new TextContent(
                    text: 'Error: Invalid patch format. The patch must start with "diff --git a/".',
                ),
            ], isError: true);
        }

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
            $result = $this->commandsExecutor->applyPatch($path, $patch);

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
