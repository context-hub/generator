<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\MCP\Tools\RagStore;

use Butschster\ContextGenerator\Rag\Document\DocumentType;
use Butschster\ContextGenerator\Rag\MCP\Tools\RagStore\Dto\RagStoreRequest;
use Butschster\ContextGenerator\Rag\Service\IndexerService;

final readonly class RagStoreHandler
{
    public function __construct(
        private IndexerService $indexer,
    ) {}

    public function handle(RagStoreRequest $request): string
    {
        if (\trim($request->content) === '') {
            throw new \InvalidArgumentException('Content cannot be empty');
        }

        $type = DocumentType::tryFrom($request->type) ?? DocumentType::General;

        $result = $this->indexer->index(
            content: $request->content,
            type: $type,
            sourcePath: $request->sourcePath,
            tags: $request->getParsedTags(),
        );

        return \sprintf(
            "Stored in knowledge base.\nType: %s | Chunks: %d | Time: %.2fms",
            $type->value,
            $result->chunksCreated,
            $result->processingTimeMs,
        );
    }
}
