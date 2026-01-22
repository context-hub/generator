# Stage 1: Configuration Infrastructure

## Overview

Create the foundational configuration classes for the multi-server, multi-collection RAG architecture. This stage
establishes the data structures that all subsequent stages will depend on, including backward compatibility with the
legacy single-store format.

## Files

**CREATE:**

- `rag/Config/ServerConfig.php` - Server connection settings (driver, endpoint, apiKey, dimensions, distance)
- `rag/Config/CollectionConfig.php` - Named collection referencing a server with optional transformer overrides
- `tests/src/Rag/Config/ServerConfigTest.php` - Unit tests for ServerConfig
- `tests/src/Rag/Config/CollectionConfigTest.php` - Unit tests for CollectionConfig

**MODIFY:**

- `rag/Config/RagConfig.php` - Add servers/collections arrays, keep vectorizer/transformer as globals
- `rag/RagParserPlugin.php` - Parse new format with legacy fallback detection
- `json-schema.json` - Add new configuration schema structure

## Code References

### Pattern for Config Classes

```php
// rag/Config/StoreConfig.php:8-28 - Pattern for readonly config with fromArray()
final readonly class StoreConfig
{
    public function __construct(
        public string $driver = 'qdrant',
        public string $endpointUrl = 'http://localhost:6333',
        // ...
    ) {}

    public static function fromArray(array $data): self
    {
        // Parse with defaults
    }
}
```

### Pattern for Parser Plugin

```php
// rag/RagParserPlugin.php:20-48 - Pattern for parsing and registry update
public function parse(array $config, string $rootPath): ?RegistryInterface
{
    if (!$this->supports($config)) {
        return null;
    }
    // Parse config
    $ragConfig = RagConfig::fromArray($config['rag']);
    $this->registry->setConfig($ragConfig);
    return $this->registry;
}
```

## Implementation Details

### 1. ServerConfig Class

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Config;

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
```

### 2. CollectionConfig Class

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Config;

final readonly class CollectionConfig
{
    public function __construct(
        public string $name,
        public string $server,
        public string $collection,
        public ?string $description = null,
        public ?int $embeddingsDimension = null,      // Override from server
        public ?string $embeddingsDistance = null,    // Override from server
        public ?TransformerConfig $transformer = null, // Override global
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
     * Get effective transformer config (collection override or fallback)
     */
    public function getTransformer(TransformerConfig $fallback): TransformerConfig
    {
        return $this->transformer ?? $fallback;
    }
}
```

### 3. Updated RagConfig Class

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Config;

final readonly class RagConfig
{
    /**
     * @param array<string, ServerConfig> $servers
     * @param array<string, CollectionConfig> $collections
     */
    public function __construct(
        public bool $enabled = false,
        public array $servers = [],
        public array $collections = [],
        public VectorizerConfig $vectorizer = new VectorizerConfig(),
        public TransformerConfig $transformer = new TransformerConfig(),
        // Legacy support - kept for backward compatibility
        public ?StoreConfig $store = null,
    ) {}

    public static function fromArray(array $data): self
    {
        // Detect legacy vs new format
        if (isset($data['store'])) {
            return self::fromLegacyArray($data);
        }

        return self::fromNewArray($data);
    }

    private static function fromLegacyArray(array $data): self
    {
        $storeConfig = StoreConfig::fromArray($data['store'] ?? []);

        // Convert legacy to new structure
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
            store: $storeConfig, // Keep for backward compat
        );
    }

    private static function fromNewArray(array $data): self
    {
        $servers = [];
        foreach (($data['servers'] ?? []) as $name => $serverData) {
            $servers[$name] = ServerConfig::fromArray($name, $serverData);
        }

        $collections = [];
        foreach (($data['collections'] ?? []) as $name => $collectionData) {
            $collections[$name] = CollectionConfig::fromArray($name, $collectionData);
        }

        return new self(
            enabled: (bool) ($data['enabled'] ?? false),
            servers: $servers,
            collections: $collections,
            vectorizer: VectorizerConfig::fromArray($data['vectorizer'] ?? []),
            transformer: TransformerConfig::fromArray($data['transformer'] ?? []),
        );
    }

    public function getServer(string $name): ServerConfig
    {
        if (!isset($this->servers[$name])) {
            throw new \InvalidArgumentException(\sprintf('Server "%s" not found', $name));
        }
        return $this->servers[$name];
    }

    public function getCollection(string $name): CollectionConfig
    {
        if (!isset($this->collections[$name])) {
            throw new \InvalidArgumentException(\sprintf('Collection "%s" not found', $name));
        }
        return $this->collections[$name];
    }

    public function hasCollection(string $name): bool
    {
        return isset($this->collections[$name]);
    }

    /**
     * @return string[]
     */
    public function getCollectionNames(): array
    {
        return \array_keys($this->collections);
    }
}
```

### 4. JSON Schema Update

Add to `json-schema.json` under `properties.rag.properties`:

```json
{
  "servers": {
    "type": "object",
    "description": "Named server connections",
    "additionalProperties": {
      "type": "object",
      "properties": {
        "driver": {
          "type": "string",
          "enum": [
            "qdrant",
            "memory"
          ],
          "default": "qdrant"
        },
        "endpoint_url": {
          "type": "string",
          "default": "http://localhost:6333"
        },
        "api_key": {
          "type": "string"
        },
        "embeddings_dimension": {
          "type": "integer",
          "default": 1536
        },
        "embeddings_distance": {
          "type": "string",
          "enum": [
            "Cosine",
            "Euclid",
            "Dot"
          ],
          "default": "Cosine"
        }
      }
    }
  },
  "collections": {
    "type": "object",
    "description": "Named collections referencing servers",
    "additionalProperties": {
      "type": "object",
      "required": [
        "server",
        "collection"
      ],
      "properties": {
        "server": {
          "type": "string",
          "description": "Reference to server name"
        },
        "collection": {
          "type": "string",
          "description": "Qdrant collection name"
        },
        "description": {
          "type": "string"
        },
        "embeddings_dimension": {
          "type": "integer"
        },
        "embeddings_distance": {
          "type": "string"
        },
        "transformer": {
          "$ref": "#/definitions/ragTransformer"
        }
      }
    }
  }
}
```

## Definition of Done

- [ ] `ServerConfig` class created with `fromArray()` static constructor
- [ ] `CollectionConfig` class created with server reference and transformer override support
- [ ] `RagConfig` updated to hold servers/collections maps
- [ ] Legacy format detection works (`store` key triggers old parsing)
- [ ] New format parsing works with validation
- [ ] `RagConfig::getServer()`, `getCollection()`, `hasCollection()`, `getCollectionNames()` methods work
- [ ] JSON schema updated with new structure
- [ ] Unit tests pass for both legacy and new config formats
- [ ] Existing RAG commands still work with legacy config

## Dependencies

**Requires**: None (foundation stage)
**Enables**: Stage 2 (Store Registry), Stage 3 (Service Layer), Stage 4 (Tool Parser)
