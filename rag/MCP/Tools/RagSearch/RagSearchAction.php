<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\MCP\Tools\RagSearch;

use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Butschster\ContextGenerator\Rag\MCP\Tools\RagSearch\Dto\RagSearchRequest;
use PhpMcp\Schema\Result\CallToolResult;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'rag-search',
    description: 'Search the project knowledge base using natural language. Returns relevant documentation, code explanations, and insights.',
    title: 'RAG Search',
)]
#[InputSchema(class: RagSearchRequest::class)]
final readonly class RagSearchAction
{
    public function __construct(
        private RagSearchHandler $handler,
        private LoggerInterface $logger,
    ) {}

    #[Post(path: '/tools/call/rag-search', name: 'tools.rag-search')]
    public function __invoke(RagSearchRequest $request): CallToolResult
    {
        $this->logger->info('RAG Search', [
            'query' => $request->query,
            'type' => $request->type,
            'limit' => $request->limit,
        ]);

        try {
            return ToolResult::text($this->handler->handle($request));
        } catch (\Throwable $e) {
            $this->logger->error('RAG Search error', ['error' => $e->getMessage()]);
            return ToolResult::error($e->getMessage());
        }
    }
}
