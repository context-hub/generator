<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Store;

use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Butschster\ContextGenerator\Rag\Config\CollectionConfig;
use Butschster\ContextGenerator\Rag\Config\RagConfig;
use Butschster\ContextGenerator\Rag\Config\ServerConfig;
use Butschster\ContextGenerator\Rag\Config\StoreConfig;
use Symfony\AI\Store\Bridge\Qdrant\Store as QdrantStore;
use Symfony\AI\Store\InMemory\Store as InMemoryStore;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\HttpClient\HttpClient;

final readonly class StoreFactory
{
    public function __construct(
        private VariableResolver $variableResolver,
    ) {}

    /**
     * Create store for a specific collection using new config structure.
     */
    public function createForCollection(
        ServerConfig $serverConfig,
        CollectionConfig $collectionConfig,
    ): StoreInterface {
        return match ($serverConfig->driver) {
            'qdrant' => $this->createQdrantStore($serverConfig, $collectionConfig),
            'memory', 'in_memory' => new InMemoryStore(),
            default => throw new \InvalidArgumentException(
                \sprintf('Unknown RAG store driver: %s', $serverConfig->driver),
            ),
        };
    }

    /**
     * Create store from RagConfig (supports both legacy and new format).
     */
    public function create(RagConfig $config): StoreInterface
    {
        // New format: use first collection
        if (!empty($config->collections)) {
            $collectionName = \array_key_first($config->collections);
            $collectionConfig = $config->getCollection($collectionName);
            $serverConfig = $config->getServer($collectionConfig->server);

            return $this->createForCollection($serverConfig, $collectionConfig);
        }

        // Legacy format: use store config directly
        if ($config->store !== null) {
            return $this->createFromLegacyStoreConfig($config->store);
        }

        throw new \InvalidArgumentException('No collections or legacy store config found');
    }

    private function createQdrantStore(
        ServerConfig $serverConfig,
        CollectionConfig $collectionConfig,
    ): QdrantStore {
        $endpointUrl = $this->variableResolver->resolve($serverConfig->endpointUrl);
        $apiKey = $this->variableResolver->resolve($serverConfig->apiKey);
        $collectionName = $this->variableResolver->resolve($collectionConfig->collection);

        // Use collection-level overrides or server defaults
        $dimension = $collectionConfig->getEffectiveEmbeddingsDimension($serverConfig);
        $distance = $collectionConfig->getEffectiveEmbeddingsDistance($serverConfig);

        return new QdrantStore(
            httpClient: HttpClient::create(),
            endpointUrl: $endpointUrl,
            apiKey: $apiKey,
            collectionName: $collectionName,
            embeddingsDimension: $dimension,
            embeddingsDistance: $distance,
        );
    }

    private function createFromLegacyStoreConfig(StoreConfig $config): StoreInterface
    {
        return match ($config->driver) {
            'qdrant' => new QdrantStore(
                httpClient: HttpClient::create(),
                endpointUrl: $this->variableResolver->resolve($config->endpointUrl),
                apiKey: $this->variableResolver->resolve($config->apiKey),
                collectionName: $this->variableResolver->resolve($config->collection),
                embeddingsDimension: $config->embeddingsDimension,
                embeddingsDistance: $config->embeddingsDistance,
            ),
            'memory', 'in_memory' => new InMemoryStore(),
            default => throw new \InvalidArgumentException(
                \sprintf('Unknown RAG store driver: %s', $config->driver),
            ),
        };
    }
}
