<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileWrite;

use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileWrite\Dto\FileWriteRequest;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use PhpMcp\Schema\Result\CallToolResult;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'file-write',
    description: 'Write content to a file (mostly new files, use apply-path for updates if possible). Can create parent directories automatically.',
    title: 'File Write',
)]
#[InputSchema(class: FileWriteRequest::class)]
final readonly class FileWriteAction
{
    public function __construct(
        private LoggerInterface $logger,
        private FileWriteHandler $handler,
    ) {}

    #[Post(path: '/tools/call/file-write', name: 'tools.file-write')]
    public function __invoke(FileWriteRequest $request): CallToolResult
    {
        $this->logger->info('Processing file-write tool');

        $result = $this->handler->handle($request);

        if (!$result->success) {
            return ToolResult::error($result->error ?? 'Unknown error');
        }

        return ToolResult::text($result->message ?? 'File written successfully');
    }
}
