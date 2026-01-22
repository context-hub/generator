<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Config;

/**
 * Configuration for a named RAG collection.
 *
 * A collection references a server and defines:
 * - The Qdrant collection name
 * - Optional description (used in tool descriptions)
 * - Optional embedding settings overrides
 * - Optional transformer settings overrides
 */
final readonly class CollectionConfig
{
    public function __construct(
        public string $name,
        public string $server,
        public string $collection,
        public ?string $description = null,
        public ?int $embeddingsDimension = null,
        public ?string $embeddingsDistance = null,
        public ?TransformerConfig $transformer = null,
    ) {}

    public static function fromArray(string $name, array $data): self
    {
        return new self(
            name: $name,
            server: (string) ($data['server'] ?? 'default'),
            collection: (string) ($data['collection'] ?? $name),
            description: isset($data['description']) ? (string) $data['description'] : null,
            embeddingsDimension: isset($data['embeddings_dimension']) ? (int) $data['embeddings_dimension'] : null,
            embeddingsDistance: isset($data['embeddings_distance']) ? (string) $data['embeddings_distance'] : null,
            transformer: isset($data['transformer']) ? TransformerConfig::fromArray($data['transformer']) : null,
        );
    }

    /**
     * Get effective transformer config (collection override or fallback to global).
     */
    public function getTransformer(TransformerConfig $fallback): TransformerConfig
    {
        return $this->transformer ?? $fallback;
    }

    /**
     * Get effective embeddings dimension (collection override or fallback to server).
     */
    public function getEffectiveEmbeddingsDimension(ServerConfig $server): int
    {
        return $this->embeddingsDimension ?? $server->embeddingsDimension;
    }

    /**
     * Get effective embeddings distance (collection override or fallback to server).
     */
    public function getEffectiveEmbeddingsDistance(ServerConfig $server): string
    {
        return $this->embeddingsDistance ?? $server->embeddingsDistance;
    }
}
