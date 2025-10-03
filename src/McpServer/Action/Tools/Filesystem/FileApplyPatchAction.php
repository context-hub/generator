<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem;

use Butschster\ContextGenerator\Lib\Git\CommandsExecutorInterface;
use Butschster\ContextGenerator\Lib\Git\Exception\GitCommandException;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\Dto\FileApplyPatchRequest;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use PhpMcp\Schema\Result\CallToolResult;
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
        if (!\str_starts_with(haystack: $patch, needle: 'diff --git a/')) {
            return ToolResult::error(error: 'Invalid patch format. The patch must start with "diff --git a/".');
        }

        if (empty($path)) {
            return ToolResult::error(error: 'Missing path parameter');
        }

        if (empty($patch)) {
            return ToolResult::error(error: 'Missing patch parameter');
        }

        try {
            $result = $this->commandsExecutor->applyPatch($path, $patch);

            return ToolResult::text(text: $result);
        } catch (GitCommandException $e) {
            $this->logger->error('Error applying git patch', [
                'path' => $path,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return ToolResult::error(error: $e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error applying git patch', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error(error: $e->getMessage());
        }
    }
}
