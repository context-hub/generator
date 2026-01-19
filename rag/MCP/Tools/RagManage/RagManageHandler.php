<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\MCP\Tools\RagManage;

use Butschster\ContextGenerator\Rag\MCP\Tools\RagManage\Dto\RagManageRequest;
use Butschster\ContextGenerator\Rag\RagRegistryInterface;

final readonly class RagManageHandler
{
    public function __construct(
        private RagRegistryInterface $registry,
    ) {}

    public function handle(RagManageRequest $request): string
    {
        return match ($request->action) {
            'stats' => $this->stats(),
            default => \sprintf('Unknown action: %s. Available actions: stats', $request->action),
        };
    }

    private function stats(): string
    {
        $config = $this->registry->getConfig();

        $lines = [
            'RAG Knowledge Base Status',
            '=========================',
            '',
            \sprintf('Enabled: %s', $config->enabled ? 'Yes' : 'No'),
            '',
            'Store Configuration:',
            \sprintf('  Driver: %s', $config->store->driver),
            \sprintf('  Endpoint: %s', $config->store->endpointUrl),
            \sprintf('  Collection: %s', $config->store->collection),
            \sprintf('  Dimensions: %d', $config->store->embeddingsDimension),
            '',
            'Vectorizer Configuration:',
            \sprintf('  Platform: %s', $config->vectorizer->platform),
            \sprintf('  Model: %s', $config->vectorizer->model),
            '',
            'Transformer Configuration:',
            \sprintf('  Chunk Size: %d', $config->transformer->chunkSize),
            \sprintf('  Overlap: %d', $config->transformer->overlap),
        ];

        return \implode("\n", $lines);
    }
}
