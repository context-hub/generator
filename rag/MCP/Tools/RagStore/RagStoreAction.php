<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\MCP\Tools\RagStore;

use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Butschster\ContextGenerator\Rag\MCP\Tools\RagStore\Dto\RagStoreRequest;
use PhpMcp\Schema\Result\CallToolResult;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'rag-store',
    description: 'Store documentation, code explanations, or insights in the project knowledge base for later retrieval.',
    title: 'RAG Store',
)]
#[InputSchema(class: RagStoreRequest::class)]
final readonly class RagStoreAction
{
    public function __construct(
        private RagStoreHandler $handler,
        private LoggerInterface $logger,
    ) {}

    #[Post(path: '/tools/call/rag-store', name: 'tools.rag-store')]
    public function __invoke(RagStoreRequest $request): CallToolResult
    {
        $this->logger->info('RAG Store', [
            'type' => $request->type,
            'sourcePath' => $request->sourcePath,
        ]);

        try {
            return ToolResult::text($this->handler->handle($request));
        } catch (\Throwable $e) {
            $this->logger->error('RAG Store error', ['error' => $e->getMessage()]);
            return ToolResult::error($e->getMessage());
        }
    }
}
