# Stage 2: Store Registry & Factory

## Overview

Create a registry to manage multiple store instances, one per collection. The registry lazily creates stores on first
access and caches them for reuse. The `StoreFactory` is updated to work with the new config structure.

## Files

**CREATE:**

- `rag/Store/StoreRegistryInterface.php` - Interface for store management
- `rag/Store/StoreRegistry.php` - Implementation with lazy store creation
- `tests/src/Rag/Store/StoreRegistryTest.php` - Unit tests

**MODIFY:**

- `rag/Store/StoreFactory.php` - Accept ServerConfig + CollectionConfig instead of RagConfig
- `rag/RagBootloader.php` - Register StoreRegistry in container

## Code References

### Pattern for Registry

```php
// rag/RagRegistry.php:14-50 - Pattern for config registry with interface
final class RagRegistry implements RagRegistryInterface
{
    private ?RagConfig $config = null;

    public function setConfig(RagConfig $config): void
    {
        $this->config = $config;
    }

    public function isEnabled(): bool
    {
        return $this->config?->enabled ?? false;
    }
}
```

### Pattern for Factory

```php
// rag/Store/StoreFactory.php:12-35 - Current factory pattern
public function create(RagConfig $config): StoreInterface
{
    return match ($config->store->driver) {
        'qdrant' => new QdrantStore(/* ... */),
        'memory', 'in_memory' => new InMemoryStore(),
        default => throw new \InvalidArgumentException(/* ... */),
    };
}
```

### Pattern for Bootloader Registration

```php
// rag/RagBootloader.php:30-45 - Pattern for singleton registration
$container->bindSingleton(
    RagRegistryInterface::class,
    static fn() => new RagRegistry($logger),
);
```

## Implementation Details

### 1. StoreRegistryInterface

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Store;

use Butschster\ContextGenerator\Rag\Config\CollectionConfig;
use Symfony\AI\Store\StoreInterface;

interface StoreRegistryInterface
{
    /**
     * Get store for a specific collection.
     * Creates the store lazily on first access.
     *
     * @throws \InvalidArgumentException If collection doesn't exist
     */
    public function getStore(string $collectionName): StoreInterface;

    /**
     * Check if a collection exists in configuration.
     */
    public function hasCollection(string $collectionName): bool;

    /**
     * Get collection configuration.
     *
     * @throws \InvalidArgumentException If collection doesn't exist
     */
    public function getCollectionConfig(string $collectionName): CollectionConfig;

    /**
     * Get all collection names.
     *
     * @return string[]
     */
    public function getCollectionNames(): array;
}
```

### 2. StoreRegistry Implementation

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Store;

use Butschster\ContextGenerator\Rag\Config\CollectionConfig;
use Butschster\ContextGenerator\Rag\Config\RagConfig;
use Psr\Log\LoggerInterface;
use Symfony\AI\Store\StoreInterface;

final class StoreRegistry implements StoreRegistryInterface
{
    /** @var array<string, StoreInterface> */
    private array $stores = [];

    public function __construct(
        private readonly RagConfig $config,
        private readonly StoreFactory $factory,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function getStore(string $collectionName): StoreInterface
    {
        if (!$this->hasCollection($collectionName)) {
            throw new \InvalidArgumentException(
                \sprintf('Collection "%s" not found in RAG configuration', $collectionName),
            );
        }

        // Lazy creation with caching
        if (!isset($this->stores[$collectionName])) {
            $this->stores[$collectionName] = $this->createStore($collectionName);
        }

        return $this->stores[$collectionName];
    }

    public function hasCollection(string $collectionName): bool
    {
        return $this->config->hasCollection($collectionName);
    }

    public function getCollectionConfig(string $collectionName): CollectionConfig
    {
        return $this->config->getCollection($collectionName);
    }

    public function getCollectionNames(): array
    {
        return $this->config->getCollectionNames();
    }

    private function createStore(string $collectionName): StoreInterface
    {
        $collectionConfig = $this->config->getCollection($collectionName);
        $serverConfig = $this->config->getServer($collectionConfig->server);

        $this->logger?->debug('Creating store for collection', [
            'collection' => $collectionName,
            'server' => $collectionConfig->server,
            'driver' => $serverConfig->driver,
        ]);

        return $this->factory->createForCollection($serverConfig, $collectionConfig);
    }
}
```

### 3. Updated StoreFactory

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Store;

use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Butschster\ContextGenerator\Rag\Config\CollectionConfig;
use Butschster\ContextGenerator\Rag\Config\RagConfig;
use Butschster\ContextGenerator\Rag\Config\ServerConfig;
use Qdrant\Config;
use Qdrant\Http\GuzzleClient;
use Qdrant\Qdrant;
use Symfony\AI\Store\Bridge\Qdrant\Store as QdrantStore;
use Symfony\AI\Store\InMemoryStore;
use Symfony\AI\Store\StoreInterface;

final readonly class StoreFactory
{
    public function __construct(
        private VariableResolver $variableResolver,
    ) {}

    /**
     * Create store for a specific collection.
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
     * Legacy method for backward compatibility.
     * @deprecated Use createForCollection() instead
     */
    public function create(RagConfig $config): StoreInterface
    {
        // Use default collection for legacy support
        if (!empty($config->collections)) {
            $collectionName = \array_key_first($config->collections);
            $collectionConfig = $config->getCollection($collectionName);
            $serverConfig = $config->getServer($collectionConfig->server);
            return $this->createForCollection($serverConfig, $collectionConfig);
        }

        // Fallback to old store config if present
        if ($config->store !== null) {
            return $this->createFromLegacyConfig($config);
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
        $dimension = $collectionConfig->embeddingsDimension ?? $serverConfig->embeddingsDimension;
        $distance = $collectionConfig->embeddingsDistance ?? $serverConfig->embeddingsDistance;

        $parsedUrl = \parse_url($endpointUrl);
        $host = $parsedUrl['host'] ?? 'localhost';
        $port = $parsedUrl['port'] ?? 6333;
        $scheme = $parsedUrl['scheme'] ?? 'http';
        $isHttps = $scheme === 'https';

        $qdrantConfig = new Config(
            host: $host,
            port: $port,
            tls: $isHttps,
        );

        if ($apiKey !== '') {
            $qdrantConfig->setApiKey($apiKey);
        }

        return new QdrantStore(
            qdrant: new Qdrant(new GuzzleClient($qdrantConfig)),
            collectionName: $collectionName,
            vectorName: 'default',
            vectorDimension: $dimension,
            vectorDistance: $distance,
        );
    }

    /**
     * @deprecated Remove after full migration
     */
    private function createFromLegacyConfig(RagConfig $config): StoreInterface
    {
        $storeConfig = $config->store;
        \assert($storeConfig !== null);

        return match ($storeConfig->driver) {
            'qdrant' => $this->createQdrantStoreFromLegacy($storeConfig),
            'memory', 'in_memory' => new InMemoryStore(),
            default => throw new \InvalidArgumentException(
                \sprintf('Unknown RAG store driver: %s', $storeConfig->driver),
            ),
        };
    }

    private function createQdrantStoreFromLegacy(\Butschster\ContextGenerator\Rag\Config\StoreConfig $config): QdrantStore
    {
        // ... existing implementation from current StoreFactory
    }
}
```

### 4. Updated RagBootloader

Add to `RagBootloader::defineSingletons()`:

```php
// Store registry for multi-collection support
$container->bindSingleton(
    StoreRegistryInterface::class,
    static function (RagRegistryInterface $ragRegistry, StoreFactory $factory, LoggerInterface $logger) {
        $config = $ragRegistry->getConfig();
        return new StoreRegistry($config, $factory, $logger);
    },
);

// Legacy StoreInterface binding - uses default collection
$container->bindSingleton(
    StoreInterface::class,
    static function (StoreRegistryInterface $storeRegistry, RagRegistryInterface $ragRegistry) {
        $collectionNames = $storeRegistry->getCollectionNames();
        if (empty($collectionNames)) {
            throw new \RuntimeException('No RAG collections configured');
        }
        // Use first collection as default for backward compatibility
        return $storeRegistry->getStore($collectionNames[0]);
    },
);
```

## Definition of Done

- [ ] `StoreRegistryInterface` created with all required methods
- [ ] `StoreRegistry` implementation with lazy store creation and caching
- [ ] `StoreFactory::createForCollection()` method works with new config structure
- [ ] Legacy `StoreFactory::create()` method still works for backward compatibility
- [ ] `StoreRegistry` registered in container via `RagBootloader`
- [ ] Legacy `StoreInterface` binding uses default/first collection
- [ ] Unit tests verify lazy creation and caching behavior
- [ ] Multiple stores can be created for different collections

## Dependencies

**Requires**: Stage 1 (Configuration Infrastructure)
**Enables**: Stage 3 (Service Layer), Stage 5 (Dynamic Tools)
