<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\DirectoryList;

use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\DirectoryList\Dto\DirectoryListRequest;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use PhpMcp\Schema\Result\CallToolResult;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'directory-list',
    description: 'List directories and files with filtering options using Symfony Finder. Always ask for source path.',
    title: 'Directory List',
)]
#[InputSchema(class: DirectoryListRequest::class)]
final readonly class DirectoryListAction
{
    public function __construct(
        private DirectoryListHandler $handler,
        private LoggerInterface $logger,
    ) {}

    #[Post(path: '/tools/call/directory-list', name: 'tools.directory-list')]
    public function __invoke(DirectoryListRequest $request): CallToolResult
    {
        $this->logger->info('Processing directory-list tool');

        $result = $this->handler->handle($request);

        if (!$result->success) {
            return ToolResult::error($result->error ?? 'Unknown error');
        }

        return ToolResult::success($result->toResponseData());
    }
}
