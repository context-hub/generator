<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Tool;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\McpServer\Tool\RagToolExecutorInterface;
use Butschster\ContextGenerator\Rag\MCP\Tools\RagSearch\Dto\RagSearchRequest;
use Butschster\ContextGenerator\Rag\MCP\Tools\RagSearch\RagSearchHandler;
use Butschster\ContextGenerator\Rag\MCP\Tools\RagStore\Dto\RagStoreRequest;
use Butschster\ContextGenerator\Rag\MCP\Tools\RagStore\RagStoreHandler;
use Butschster\ContextGenerator\Rag\RagRegistryInterface;
use Butschster\ContextGenerator\Rag\Service\ServiceFactory;
use Psr\Log\LoggerInterface;

/**
 * Implementation of RagToolExecutorInterface that bridges MCP tool execution
 * with the RAG service layer.
 */
#[LoggerPrefix(prefix: 'rag-tool-executor')]
final readonly class RagToolExecutor implements RagToolExecutorInterface
{
    public function __construct(
        private RagRegistryInterface $ragRegistry,
        private ServiceFactory $serviceFactory,
        private LoggerInterface $logger,
    ) {}

    public function search(string $collection, array $arguments): array
    {
        $this->logger->info('Executing RAG search', [
            'collection' => $collection,
            'query' => $arguments['query'] ?? null,
        ]);

        try {
            $request = new RagSearchRequest(
                query: $arguments['query'] ?? '',
                type: $arguments['type'] ?? null,
                sourcePath: $arguments['sourcePath'] ?? null,
                limit: isset($arguments['limit']) ? (int) $arguments['limit'] : 10,
            );

            $handler = new RagSearchHandler($this->serviceFactory, $collection);
            $output = $handler->handle($request);

            return [
                'output' => $output,
                'success' => true,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('RAG search failed', [
                'collection' => $collection,
                'error' => $e->getMessage(),
            ]);

            return [
                'output' => 'Search failed: ' . $e->getMessage(),
                'success' => false,
            ];
        }
    }

    public function store(string $collection, array $arguments): array
    {
        $this->logger->info('Executing RAG store', [
            'collection' => $collection,
            'type' => $arguments['type'] ?? 'general',
        ]);

        try {
            $request = new RagStoreRequest(
                content: $arguments['content'] ?? '',
                type: $arguments['type'] ?? 'general',
                sourcePath: $arguments['sourcePath'] ?? null,
                tags: $arguments['tags'] ?? null,
            );

            $handler = new RagStoreHandler($this->serviceFactory, $collection);
            $output = $handler->handle($request);

            return [
                'output' => $output,
                'success' => true,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('RAG store failed', [
                'collection' => $collection,
                'error' => $e->getMessage(),
            ]);

            return [
                'output' => 'Store failed: ' . $e->getMessage(),
                'success' => false,
            ];
        }
    }

    public function hasCollection(string $collection): bool
    {
        return $this->ragRegistry->getConfig()->hasCollection($collection);
    }
}
