<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Tool;

use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\Rag\MCP\Tools\RagSearch\Dto\RagSearchRequest;
use Butschster\ContextGenerator\Rag\MCP\Tools\RagSearch\RagSearchHandler;
use Butschster\ContextGenerator\Rag\Service\ServiceFactory;
use PhpMcp\Schema\Result\CallToolResult;
use Psr\Log\LoggerInterface;

/**
 * Dynamic RAG search action configured from context.yaml.
 *
 * Unlike the static RagSearchAction, this can be instantiated multiple times
 * with different configurations for different collections.
 */
final readonly class DynamicRagSearchAction
{
    public function __construct(
        private RagToolConfig $config,
        private ServiceFactory $serviceFactory,
        private LoggerInterface $logger,
    ) {}

    public function getToolId(): string
    {
        return $this->config->getSearchToolId();
    }

    public function getToolName(): string
    {
        return $this->config->getName();
    }

    public function getToolDescription(): string
    {
        return $this->config->description;
    }

    public function getToolTitle(): string
    {
        return $this->config->getName() ??  \ucfirst(\str_replace(['-', '_'], ' ', $this->config->id));
    }

    /**
     * Get JSON schema for this tool's input.
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Search query in natural language',
                ],
                'type' => [
                    'type' => 'string',
                    'description' => 'Filter by type: architecture, api, testing, convention, tutorial, reference, general',
                ],
                'sourcePath' => [
                    'type' => 'string',
                    'description' => 'Filter by source path (exact or prefix match)',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of results to return',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 50,
                ],
            ],
            'required' => ['query'],
        ];
    }

    public function __invoke(RagSearchRequest $request): CallToolResult
    {
        $this->logger->info('Dynamic RAG Search', [
            'tool' => $this->config->id,
            'collection' => $this->config->collection,
            'query' => $request->query,
        ]);

        try {
            $handler = new RagSearchHandler($this->serviceFactory, $this->config->collection);

            return ToolResult::text($handler->handle($request));
        } catch (\Throwable $e) {
            $this->logger->error('Dynamic RAG Search error', [
                'tool' => $this->config->id,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error($e->getMessage());
        }
    }
}
