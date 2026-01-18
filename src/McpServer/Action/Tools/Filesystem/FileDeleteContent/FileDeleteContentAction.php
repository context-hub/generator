<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileDeleteContent;

use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileDeleteContent\Dto\FileDeleteContentRequest;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use PhpMcp\Schema\Result\CallToolResult;
use Psr\Log\LoggerInterface;

/**
 * MCP tool for deleting content at specific line numbers in files.
 */
#[Tool(
    name: 'file-delete-content',
    description: 'Delete specific line(s) from a file. Supports individual lines or ranges.',
    title: 'File Delete Content',
)]
#[InputSchema(class: FileDeleteContentRequest::class)]
final readonly class FileDeleteContentAction
{
    public function __construct(
        private FileDeleteContentHandler $handler,
        private LoggerInterface $logger,
    ) {}

    #[Post(path: '/tools/call/file-delete-content', name: 'tools.file-delete-content')]
    public function __invoke(FileDeleteContentRequest $request): CallToolResult
    {
        $this->logger->info('Processing file-delete-content tool', [
            'path' => $request->path,
            'linesCount' => \count($request->lines),
        ]);

        // Validate required parameters
        if (empty($request->path)) {
            return ToolResult::error('Missing path parameter');
        }

        if (empty($request->lines)) {
            return ToolResult::error('Missing lines parameter - must provide at least one line number or range');
        }

        try {
            $result = $this->handler->handle($request);

            if ($result->success) {
                return ToolResult::text($result->message ?? 'Content deleted successfully');
            }

            return ToolResult::error($result->error ?? 'Unknown error occurred');
        } catch (\Throwable $e) {
            $this->logger->error('Error deleting content from file', [
                'path' => $request->path,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ToolResult::error(\sprintf(
                "Failed to delete content: %s",
                $e->getMessage(),
            ));
        }
    }
}
