<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Config;

/**
 * Root configuration for the RAG module.
 *
 * Supports two formats:
 * - Legacy format: single `store` with driver-specific config
 * - New format: multiple `servers` and `collections`
 *
 * Legacy format is automatically converted to new format for internal use.
 */
final readonly class RagConfig
{
    /**
     * @param array<string, ServerConfig> $servers Named server configurations
     * @param array<string, CollectionConfig> $collections Named collection configurations
     * @param StoreConfig|null $store Legacy store config (for backward compatibility)
     */
    public function __construct(
        public bool $enabled = false,
        public array $servers = [],
        public array $collections = [],
        public VectorizerConfig $vectorizer = new VectorizerConfig(),
        public TransformerConfig $transformer = new TransformerConfig(),
        public ?StoreConfig $store = null,
    ) {}

    public static function fromArray(array $data): self
    {
        if (isset($data['store'])) {
            return self::fromLegacyArray($data);
        }

        return self::fromNewArray($data);
    }

    /**
     * Check if configuration uses legacy format.
     */
    public function isLegacyFormat(): bool
    {
        return $this->store !== null;
    }

    /**
     * Get server by name.
     *
     * @throws \InvalidArgumentException If server not found
     */
    public function getServer(string $name): ServerConfig
    {
        if (!isset($this->servers[$name])) {
            throw new \InvalidArgumentException(\sprintf('Server "%s" not found', $name));
        }

        return $this->servers[$name];
    }

    /**
     * Get collection by name.
     *
     * @throws \InvalidArgumentException If collection not found
     */
    public function getCollection(string $name): CollectionConfig
    {
        if (!isset($this->collections[$name])) {
            throw new \InvalidArgumentException(\sprintf('Collection "%s" not found', $name));
        }

        return $this->collections[$name];
    }

    /**
     * Check if collection exists.
     */
    public function hasCollection(string $name): bool
    {
        return isset($this->collections[$name]);
    }

    /**
     * Get all collection names.
     *
     * @return string[]
     */
    public function getCollectionNames(): array
    {
        return \array_keys($this->collections);
    }

    /**
     * Get all server names.
     *
     * @return string[]
     */
    public function getServerNames(): array
    {
        return \array_keys($this->servers);
    }

    /**
     * Get server for a given collection.
     *
     * @throws \InvalidArgumentException If collection or server not found
     */
    public function getServerForCollection(string $collectionName): ServerConfig
    {
        $collection = $this->getCollection($collectionName);

        return $this->getServer($collection->server);
    }

    /**
     * Convert legacy format (single store) to new multi-server/collection format.
     */
    private static function fromLegacyArray(array $data): self
    {
        $storeConfig = StoreConfig::fromArray($data['store'] ?? []);

        $serverConfig = new ServerConfig(
            name: 'default',
            driver: $storeConfig->driver,
            endpointUrl: $storeConfig->endpointUrl,
            apiKey: $storeConfig->apiKey,
            embeddingsDimension: $storeConfig->embeddingsDimension,
            embeddingsDistance: $storeConfig->embeddingsDistance,
        );

        $collectionConfig = new CollectionConfig(
            name: 'default',
            server: 'default',
            collection: $storeConfig->collection,
        );

        return new self(
            enabled: (bool) ($data['enabled'] ?? false),
            servers: ['default' => $serverConfig],
            collections: ['default' => $collectionConfig],
            vectorizer: VectorizerConfig::fromArray($data['vectorizer'] ?? []),
            transformer: TransformerConfig::fromArray($data['transformer'] ?? []),
            store: $storeConfig,
        );
    }

    /**
     * Parse new format with explicit servers and collections.
     */
    private static function fromNewArray(array $data): self
    {
        $servers = [];
        foreach (($data['servers'] ?? []) as $name => $serverData) {
            $servers[$name] = ServerConfig::fromArray((string) $name, $serverData);
        }

        $collections = [];
        foreach (($data['collections'] ?? []) as $name => $collectionData) {
            $collections[$name] = CollectionConfig::fromArray((string) $name, $collectionData);
        }

        // If servers/collections are defined, ensure at least RAG is considered "configured"
        $enabled = (bool) ($data['enabled'] ?? false);
        if (!$enabled && (!empty($servers) || !empty($collections))) {
            $enabled = true;
        }

        return new self(
            enabled: $enabled,
            servers: $servers,
            collections: $collections,
            vectorizer: VectorizerConfig::fromArray($data['vectorizer'] ?? []),
            transformer: TransformerConfig::fromArray($data['transformer'] ?? []),
        );
    }
}
