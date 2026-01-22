<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Tool;

use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\Rag\MCP\Tools\RagStore\Dto\RagStoreRequest;
use Butschster\ContextGenerator\Rag\MCP\Tools\RagStore\RagStoreHandler;
use Butschster\ContextGenerator\Rag\Service\ServiceFactory;
use PhpMcp\Schema\Result\CallToolResult;
use Psr\Log\LoggerInterface;

/**
 * Dynamic RAG store action configured from context.yaml.
 *
 * Unlike the static RagStoreAction, this can be instantiated multiple times
 * with different configurations for different collections.
 */
final readonly class DynamicRagStoreAction
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
        return $this->config->description . ' (store operation)';
    }

    public function getToolTitle(): string
    {
        return $this->config->getName() ?? \ucfirst(\str_replace(['-', '_'], ' ', $this->config->id)) . ' Store';
    }

    /**
     * Get JSON schema for this tool's input.
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'content' => [
                    'type' => 'string',
                    'description' => 'Content to store in the knowledge base',
                ],
                'type' => [
                    'type' => 'string',
                    'description' => 'Type: architecture, api, testing, convention, tutorial, reference, general',
                    'default' => 'general',
                ],
                'sourcePath' => [
                    'type' => 'string',
                    'description' => 'Source path (e.g., "src/Auth/Service.php")',
                ],
                'tags' => [
                    'type' => 'string',
                    'description' => 'Tags (comma-separated)',
                ],
            ],
            'required' => ['content'],
        ];
    }

    public function __invoke(RagStoreRequest $request): CallToolResult
    {
        $this->logger->info('Dynamic RAG Store', [
            'tool' => $this->config->id,
            'collection' => $this->config->collection,
            'type' => $request->type,
        ]);

        try {
            $handler = new RagStoreHandler($this->serviceFactory, $this->config->collection);

            return ToolResult::text($handler->handle($request));
        } catch (\Throwable $e) {
            $this->logger->error('Dynamic RAG Store error', [
                'tool' => $this->config->id,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error($e->getMessage());
        }
    }
}
