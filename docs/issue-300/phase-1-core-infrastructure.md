# Phase 1: Core Infrastructure & Store Configuration

## Objective

Configure Symfony AI Store for CTX using the same parser plugin pattern as `Exclude`. Configuration lives in `context.yaml` under the `rag` key.

---

## Pattern Overview

Following the existing `Exclude` pattern:

```
ExcludeParserPlugin   → RagParserPlugin       (parses 'rag' section)
ExcludeRegistry       → RagRegistry           (stores parsed config + provides services)
ExcludeBootloader     → RagBootloader         (registers plugin, defines singletons)
```

---

## Configuration in context.yaml

> See [docs/config/config-system-guide.md] 

```yaml
# context.yaml
$schema: '...'

rag:
  enabled: true
  
  store:
    driver: qdrant
    qdrant:
      host: ${RAG_QDRANT_HOST:-localhost}
      port: ${RAG_QDRANT_PORT:-6333}
      collection: ${RAG_COLLECTION:-ctx_knowledge}
    
  vectorizer:
    platform: openai
    model: text-embedding-3-small
    
  transformer:
    chunk_size: 1000
    overlap: 200
```

---

## Files to Create

### 1.1 Document Helpers

#### `rag/Document/DocumentType.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Document;

enum DocumentType: string
{
    case Architecture = 'architecture';
    case Api = 'api';
    case Testing = 'testing';
    case Convention = 'convention';
    case General = 'general';
    case Tutorial = 'tutorial';
    case Reference = 'reference';
}
```

#### `rag/Document/MetadataFactory.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Document;

use Symfony\AI\Store\Document\Metadata;

final readonly class MetadataFactory
{
    public function create(
        DocumentType $type = DocumentType::General,
        ?string $sourcePath = null,
        ?array $tags = null,
        array $extra = [],
    ): Metadata {
        return new Metadata([
            'type' => $type->value,
            'source_path' => $sourcePath,
            'tags' => $tags ?? [],
            'indexed_at' => (new \DateTimeImmutable())->format(\DATE_ATOM),
            ...$extra,
        ]);
    }
}
```

### 1.2 Configuration DTOs

#### `rag/Config/RagConfig.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Config;

final readonly class RagConfig
{
    public function __construct(
        public bool $enabled = false,
        public StoreConfig $store = new StoreConfig(),
        public VectorizerConfig $vectorizer = new VectorizerConfig(),
        public TransformerConfig $transformer = new TransformerConfig(),
    ) {}
    
    public static function fromArray(array $data): self
    {
        return new self(
            enabled: (bool) ($data['enabled'] ?? false),
            store: StoreConfig::fromArray($data['store'] ?? []),
            vectorizer: VectorizerConfig::fromArray($data['vectorizer'] ?? []),
            transformer: TransformerConfig::fromArray($data['transformer'] ?? []),
        );
    }
}
```

#### `rag/Config/StoreConfig.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Config;

final readonly class StoreConfig
{
    public function __construct(
        public string $driver = 'qdrant',
        public string $host = 'localhost',
        public int $port = 6333,
        public string $collection = 'ctx_knowledge',
    ) {}
    
    public static function fromArray(array $data): self
    {
        $driver = $data['driver'] ?? 'qdrant';
        $driverConfig = $data[$driver] ?? [];
        
        return new self(
            driver: $driver,
            host: (string) ($driverConfig['host'] ?? 'localhost'),
            port: (int) ($driverConfig['port'] ?? 6333),
            collection: (string) ($driverConfig['collection'] ?? 'ctx_knowledge'),
        );
    }
}
```

#### `rag/Config/VectorizerConfig.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Config;

final readonly class VectorizerConfig
{
    public function __construct(
        public string $platform = 'openai',
        public string $model = 'text-embedding-3-small',
    ) {}
    
    public static function fromArray(array $data): self
    {
        return new self(
            platform: (string) ($data['platform'] ?? 'openai'),
            model: (string) ($data['model'] ?? 'text-embedding-3-small'),
        );
    }
}
```

#### `rag/Config/TransformerConfig.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Config;

final readonly class TransformerConfig
{
    public function __construct(
        public int $chunkSize = 1000,
        public int $overlap = 200,
    ) {}
    
    public static function fromArray(array $data): self
    {
        return new self(
            chunkSize: (int) ($data['chunk_size'] ?? 1000),
            overlap: (int) ($data['overlap'] ?? 200),
        );
    }
}
```

### 1.3 Parser Plugin (like ExcludeParserPlugin)

#### `rag/RagParserPlugin.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Config\Parser\ConfigParserPluginInterface;
use Butschster\ContextGenerator\Config\Registry\RegistryInterface;
use Butschster\ContextGenerator\Rag\Config\RagConfig;
use Psr\Log\LoggerInterface;

/**
 * Parser plugin for the 'rag' section in configuration
 */
final readonly class RagParserPlugin implements ConfigParserPluginInterface
{
    public function __construct(
        private RagRegistryInterface $registry,
        #[LoggerPrefix(prefix: 'rag-parser')]
        private ?LoggerInterface $logger = null,
    ) {}

    public function getConfigKey(): string
    {
        return 'rag';
    }

    public function supports(array $config): bool
    {
        return isset($config['rag']) && \is_array($config['rag']);
    }

    public function parse(array $config, string $rootPath): ?RegistryInterface
    {
        if (!$this->supports($config)) {
            return null;
        }

        \assert($this->registry instanceof RegistryInterface);
        
        $ragConfig = RagConfig::fromArray($config['rag']);
        $this->registry->setConfig($ragConfig);

        $this->logger?->info('Parsed RAG configuration', [
            'enabled' => $ragConfig->enabled,
            'store' => $ragConfig->store->driver,
            'vectorizer' => $ragConfig->vectorizer->platform,
        ]);

        return $this->registry;
    }

    public function updateConfig(array $config, string $rootPath): array
    {
        return $config;
    }
}
```

### 1.4 Registry (like ExcludeRegistry)

#### `rag/RagRegistryInterface.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag;

use Butschster\ContextGenerator\Rag\Config\RagConfig;

interface RagRegistryInterface
{
    public function setConfig(RagConfig $config): void;
    
    public function getConfig(): RagConfig;
    
    public function isEnabled(): bool;
}
```

#### `rag/RagRegistry.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Config\Registry\RegistryInterface;
use Butschster\ContextGenerator\Rag\Config\RagConfig;
use Psr\Log\LoggerInterface;
use Spiral\Core\Attribute\Singleton;

/**
 * Registry for RAG configuration
 *
 * @implements RegistryInterface<RagConfig>
 */
#[Singleton]
final class RagRegistry implements RagRegistryInterface, RegistryInterface
{
    private RagConfig $config;

    public function __construct(
        #[LoggerPrefix(prefix: 'rag-registry')]
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->config = new RagConfig();
    }

    public function setConfig(RagConfig $config): void
    {
        $this->config = $config;
        $this->logger?->debug('RAG config set', ['enabled' => $config->enabled]);
    }

    public function getConfig(): RagConfig
    {
        return $this->config;
    }

    public function isEnabled(): bool
    {
        return $this->config->enabled;
    }

    public function getType(): string
    {
        return 'rag';
    }

    public function getItems(): array
    {
        return [$this->config];
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator([$this->config]);
    }

    public function jsonSerialize(): array
    {
        return [
            'enabled' => $this->config->enabled,
            'store' => [
                'driver' => $this->config->store->driver,
                'host' => $this->config->store->host,
                'port' => $this->config->store->port,
                'collection' => $this->config->store->collection,
            ],
            'vectorizer' => [
                'platform' => $this->config->vectorizer->platform,
                'model' => $this->config->vectorizer->model,
            ],
        ];
    }
}
```

### 1.5 Store Factory

#### `rag/Store/StoreFactory.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Store;

use Butschster\ContextGenerator\Rag\Config\RagConfig;
use Symfony\AI\Store\StoreInterface;
use Symfony\AI\Store\Bridge\Qdrant\Store as QdrantStore;
use Symfony\AI\Store\Bridge\InMemory\Store as InMemoryStore;
use Symfony\Component\HttpClient\HttpClient;

final readonly class StoreFactory
{
    public function create(RagConfig $config): StoreInterface
    {
        return match ($config->store->driver) {
            'qdrant' => new QdrantStore(
                client: HttpClient::create(),
                host: $config->store->host,
                port: $config->store->port,
                collectionName: $config->store->collection,
            ),
            'memory', 'in_memory' => new InMemoryStore(),
            default => throw new \InvalidArgumentException(
                \sprintf('Unknown RAG store driver: %s', $config->store->driver)
            ),
        };
    }
}
```

### 1.6 Vectorizer Factory

#### `rag/Vectorizer/VectorizerFactory.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Vectorizer;

use Butschster\ContextGenerator\Rag\Config\RagConfig;
use Symfony\AI\Platform\Bridge\OpenAI\Platform as OpenAIPlatform;
use Symfony\AI\Platform\Bridge\Ollama\Platform as OllamaPlatform;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\Component\HttpClient\HttpClient;

final readonly class VectorizerFactory
{
    public function create(RagConfig $config): Vectorizer
    {
        $platform = match ($config->vectorizer->platform) {
            'openai' => new OpenAIPlatform(
                httpClient: HttpClient::create(),
                apiKey: $_ENV['OPENAI_API_KEY'] ?? '',
            ),
            'ollama' => new OllamaPlatform(
                httpClient: HttpClient::create(),
                baseUrl: $_ENV['OLLAMA_BASE_URL'] ?? 'http://localhost:11434',
            ),
            default => throw new \InvalidArgumentException(
                \sprintf('Unknown vectorizer platform: %s', $config->vectorizer->platform)
            ),
        };
        
        return new Vectorizer($platform, $config->vectorizer->model);
    }
}
```

---

## Implementation Order

1. Config DTOs: `RagConfig`, `StoreConfig`, `VectorizerConfig`, `TransformerConfig`
2. `RagRegistryInterface` → `RagRegistry`
3. `RagParserPlugin`
4. Document helpers: `DocumentType`, `MetadataFactory`
5. Factories: `StoreFactory`, `VectorizerFactory`

---

## Test Cases

### Unit Tests: `tests/src/Unit/Rag/`

```
Config/RagConfigTest.php
- test_creates_from_array
- test_defaults_when_empty

RagParserPluginTest.php
- test_supports_rag_config
- test_parses_rag_section
- test_returns_null_when_not_supported

RagRegistryTest.php
- test_stores_config
- test_is_enabled_when_config_enabled

Store/StoreFactoryTest.php
- test_creates_qdrant_store
- test_creates_in_memory_store
- test_throws_for_unknown_driver
```

---

## Definition of Done

- [ ] Config DTOs parse from array correctly
- [ ] RagParserPlugin parses 'rag' section from context.yaml
- [ ] RagRegistry stores and provides config
- [ ] StoreFactory creates Qdrant and InMemory stores
- [ ] VectorizerFactory creates OpenAI and Ollama vectorizers
- [ ] All unit tests pass

---

## Estimated Effort

| Task              | Complexity | Time   |
|-------------------|------------|--------|
| Config DTOs       | Low        | 1.5h   |
| Registry + Plugin | Medium     | 2h     |
| Document helpers  | Low        | 1h     |
| Factories         | Medium     | 2h     |
| Tests             | Medium     | 2h     |
| **Total**         |            | **~8h** |
