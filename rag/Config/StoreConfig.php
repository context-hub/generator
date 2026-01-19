<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Config;

final readonly class StoreConfig
{
    public function __construct(
        public string $driver = 'qdrant',
        public string $endpointUrl = 'http://localhost:6333',
        public string $apiKey = '',
        public string $collection = 'ctx_knowledge',
        public int $embeddingsDimension = 1536,
        public string $embeddingsDistance = 'Cosine',
    ) {}

    public static function fromArray(array $data): self
    {
        $driver = (string) ($data['driver'] ?? 'qdrant');
        $driverConfig = $data[$driver] ?? [];

        return new self(
            driver: $driver,
            endpointUrl: (string) ($driverConfig['endpoint_url'] ?? 'http://localhost:6333'),
            apiKey: (string) ($driverConfig['api_key'] ?? ''),
            collection: (string) ($driverConfig['collection'] ?? 'ctx_knowledge'),
            embeddingsDimension: (int) ($driverConfig['embeddings_dimension'] ?? 1536),
            embeddingsDistance: (string) ($driverConfig['embeddings_distance'] ?? 'Cosine'),
        );
    }
}
