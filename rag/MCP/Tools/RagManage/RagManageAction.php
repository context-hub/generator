<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\MCP\Tools\RagManage;

use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Butschster\ContextGenerator\Rag\MCP\Tools\RagManage\Dto\RagManageRequest;
use PhpMcp\Schema\Result\CallToolResult;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'rag-manage',
    description: 'Manage the project knowledge base. View statistics and configuration.',
    title: 'RAG Manage',
)]
#[InputSchema(class: RagManageRequest::class)]
final readonly class RagManageAction
{
    public function __construct(
        private RagManageHandler $handler,
        private LoggerInterface $logger,
    ) {}

    #[Post(path: '/tools/call/rag-manage', name: 'tools.rag-manage')]
    public function __invoke(RagManageRequest $request): CallToolResult
    {
        $this->logger->info('RAG Manage', ['action' => $request->action]);

        try {
            return ToolResult::text($this->handler->handle($request));
        } catch (\Throwable $e) {
            $this->logger->error('RAG Manage error', ['error' => $e->getMessage()]);
            return ToolResult::error($e->getMessage());
        }
    }
}
