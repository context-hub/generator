<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Config;

/**
 * Configuration for a RAG server connection.
 *
 * Defines connection settings for a vector store server (e.g., Qdrant).
 * Multiple servers can be defined and referenced by collections.
 */
final readonly class ServerConfig
{
    public function __construct(
        public string $name,
        public string $driver = 'qdrant',
        public string $endpointUrl = 'http://localhost:6333',
        public string $apiKey = '',
        public int $embeddingsDimension = 1536,
        public string $embeddingsDistance = 'Cosine',
    ) {}

    public static function fromArray(string $name, array $data): self
    {
        return new self(
            name: $name,
            driver: (string) ($data['driver'] ?? 'qdrant'),
            endpointUrl: (string) ($data['endpoint_url'] ?? 'http://localhost:6333'),
            apiKey: (string) ($data['api_key'] ?? ''),
            embeddingsDimension: (int) ($data['embeddings_dimension'] ?? 1536),
            embeddingsDistance: (string) ($data['embeddings_distance'] ?? 'Cosine'),
        );
    }
}
