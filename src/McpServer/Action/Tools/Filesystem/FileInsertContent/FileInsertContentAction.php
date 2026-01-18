<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileInsertContent;

use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileInsertContent\Dto\FileInsertContentRequest;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use PhpMcp\Schema\Result\CallToolResult;
use Psr\Log\LoggerInterface;

/**
 * MCP tool for inserting content at specific line numbers in files.
 */
#[Tool(
    name: 'file-insert-content',
    description: 'Insert content at specific line number(s) in a file. Supports single or batch insertions with automatic line offset calculation.',
    title: 'File Insert Content',
)]
#[InputSchema(class: FileInsertContentRequest::class)]
final readonly class FileInsertContentAction
{
    public function __construct(
        private FileInsertContentHandler $handler,
        private LoggerInterface $logger,
    ) {}

    #[Post(path: '/tools/call/file-insert-content', name: 'tools.file-insert-content')]
    public function __invoke(FileInsertContentRequest $request): CallToolResult
    {
        $this->logger->info('Processing file-insert-content tool', [
            'path' => $request->path,
            'insertionsCount' => \count($request->insertions),
            'position' => $request->position,
        ]);

        // Validate required parameters
        if (empty($request->path)) {
            return ToolResult::error('Missing path parameter');
        }

        if (empty($request->insertions)) {
            return ToolResult::error('Missing insertions parameter - must provide at least one insertion');
        }

        try {
            $result = $this->handler->handle($request);

            if ($result->success) {
                return ToolResult::text($result->message ?? 'Content inserted successfully');
            }

            return ToolResult::error($result->error ?? 'Unknown error occurred');
        } catch (\Throwable $e) {
            $this->logger->error('Error inserting content in file', [
                'path' => $request->path,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ToolResult::error(\sprintf(
                "Failed to insert content: %s",
                $e->getMessage(),
            ));
        }
    }
}
