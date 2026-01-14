<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileReplaceContent;

use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileReplaceContent\Dto\FileReplaceContentRequest;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use PhpMcp\Schema\Result\CallToolResult;
use Psr\Log\LoggerInterface;

/**
 * MCP tool for replacing content in files with automatic line ending normalization.
 */
#[Tool(
    name: 'file-replace-content',
    description: 'Replace a unique occurrence of text in a file with exact matching.',
    title: 'File Replace Content',
)]
#[InputSchema(class: FileReplaceContentRequest::class)]
final readonly class FileReplaceContentAction
{
    public function __construct(
        private FileReplaceContentHandler $handler,
        private LoggerInterface $logger,
    ) {}

    #[Post(path: '/tools/call/file-replace-content', name: 'tools.file-replace-content')]
    public function __invoke(FileReplaceContentRequest $request): CallToolResult
    {
        $this->logger->info('Processing file-replace-content tool', [
            'path' => $request->path,
            'searchLength' => \strlen($request->search),
            'replaceLength' => \strlen($request->replace),
        ]);

        // Validate required parameters
        if (empty($request->path)) {
            return ToolResult::error('Missing path parameter');
        }

        if (empty($request->search)) {
            return ToolResult::error('Missing search parameter - cannot replace empty content');
        }

        try {
            $result = $this->handler->handle($request);

            if ($result->success) {
                return ToolResult::text($result->message ?? 'Content replaced successfully');
            }

            return ToolResult::error($result->error ?? 'Unknown error occurred');
        } catch (\Throwable $e) {
            $this->logger->error('Error replacing content in file', [
                'path' => $request->path,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ToolResult::error(\sprintf(
                "Failed to replace content: %s",
                $e->getMessage(),
            ));
        }
    }
}
