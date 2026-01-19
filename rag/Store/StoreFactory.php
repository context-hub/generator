<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Store;

use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Butschster\ContextGenerator\Rag\Config\RagConfig;
use Symfony\AI\Store\Bridge\Qdrant\Store as QdrantStore;
use Symfony\AI\Store\InMemory\Store as InMemoryStore;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\HttpClient\HttpClient;

final readonly class StoreFactory
{
    public function __construct(
        private VariableResolver $variableResolver,
    ) {}

    public function create(RagConfig $config): StoreInterface
    {
        return match ($config->store->driver) {
            'qdrant' => new QdrantStore(
                httpClient: HttpClient::create(),
                endpointUrl: $this->variableResolver->resolve($config->store->endpointUrl),
                apiKey: $this->variableResolver->resolve($config->store->apiKey),
                collectionName: $this->variableResolver->resolve($config->store->collection),
                embeddingsDimension: $config->store->embeddingsDimension,
                embeddingsDistance: $config->store->embeddingsDistance,
            ),
            'memory', 'in_memory' => new InMemoryStore(),
            default => throw new \InvalidArgumentException(
                \sprintf('Unknown RAG store driver: %s', $config->store->driver),
            ),
        };
    }
}
