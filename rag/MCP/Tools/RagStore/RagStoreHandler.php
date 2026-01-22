<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\MCP\Tools\RagStore;

use Butschster\ContextGenerator\Rag\Document\DocumentType;
use Butschster\ContextGenerator\Rag\MCP\Tools\RagStore\Dto\RagStoreRequest;
use Butschster\ContextGenerator\Rag\Service\ServiceFactory;

final readonly class RagStoreHandler
{
    public function __construct(
        private ServiceFactory $serviceFactory,
        private string $collectionName = 'default',
    ) {}

    public function handle(RagStoreRequest $request): string
    {
        if (\trim($request->content) === '') {
            throw new \InvalidArgumentException('Content cannot be empty');
        }

        $type = DocumentType::tryFrom($request->type) ?? DocumentType::General;
        $indexer = $this->serviceFactory->getIndexer($this->collectionName);

        $result = $indexer->index(
            content: $request->content,
            type: $type,
            sourcePath: $request->sourcePath,
            tags: $request->getParsedTags(),
        );

        return \sprintf(
            "Stored in knowledge base [%s].\nType: %s | Chunks: %d | Time: %.2fms",
            $this->collectionName,
            $type->value,
            $result->chunksCreated,
            $result->processingTimeMs,
        );
    }

    /**
     * Create handler for specific collection.
     */
    public function withCollection(string $collectionName): self
    {
        return new self($this->serviceFactory, $collectionName);
    }
}
