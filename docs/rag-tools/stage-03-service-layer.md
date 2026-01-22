# Stage 3: Service Layer Updates

## Overview

Update `IndexerService` and `RetrieverService` to work with collection-specific configurations. Create a
`ServiceFactory` that produces services configured for specific collections. Update existing handlers to accept
collection context.

## Files

**CREATE:**

- `rag/Service/ServiceFactory.php` - Factory for creating collection-specific services
- `tests/src/Rag/Service/ServiceFactoryTest.php` - Unit tests

**MODIFY:**

- `rag/Service/IndexerService.php` - Accept TransformerConfig in constructor
- `rag/Service/RetrieverService.php` - No changes needed (already accepts StoreInterface)
- `rag/MCP/Tools/RagStore/RagStoreHandler.php` - Accept collection name parameter
- `rag/MCP/Tools/RagSearch/RagSearchHandler.php` - Accept collection name parameter
- `rag/RagBootloader.php` - Register ServiceFactory

## Code References

### Current IndexerService Constructor

```php
// rag/Service/IndexerService.php:17-30
public function __construct(
    private StoreInterface $store,
    private VectorizerInterface $vectorizer,
    private MetadataFactory $metadataFactory,
    RagConfig $config,
) {
    $this->transformer = new TextSplitTransformer(
        chunkSize: $config->transformer->chunkSize,
        overlap: $config->transformer->overlap,
    );
}
```

### Current RetrieverService Constructor

```php
// rag/Service/RetrieverService.php:13-19
public function __construct(
    StoreInterface $store,
    VectorizerInterface $vectorizer,
) {
    $this->retriever = new Retriever($vectorizer, $store);
}
```

### Handler Pattern

```php
// rag/MCP/Tools/RagStore/RagStoreHandler.php:12-17
public function __construct(
    private IndexerService $indexer,
) {}
```

## Implementation Details

### 1. ServiceFactory

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Service;

use Butschster\ContextGenerator\Rag\Config\RagConfig;
use Butschster\ContextGenerator\Rag\Config\TransformerConfig;
use Butschster\ContextGenerator\Rag\Document\MetadataFactory;
use Butschster\ContextGenerator\Rag\Store\StoreRegistryInterface;
use Butschster\ContextGenerator\Rag\Vectorizer\VectorizerFactory;
use Psr\Log\LoggerInterface;
use Symfony\AI\Store\Document\VectorizerInterface;

final class ServiceFactory
{
    private ?VectorizerInterface $vectorizer = null;

    /** @var array<string, IndexerService> */
    private array $indexers = [];

    /** @var array<string, RetrieverService> */
    private array $retrievers = [];

    public function __construct(
        private readonly RagConfig $config,
        private readonly StoreRegistryInterface $storeRegistry,
        private readonly VectorizerFactory $vectorizerFactory,
        private readonly MetadataFactory $metadataFactory,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * Get IndexerService for a specific collection.
     */
    public function getIndexer(string $collectionName): IndexerService
    {
        if (!isset($this->indexers[$collectionName])) {
            $this->indexers[$collectionName] = $this->createIndexer($collectionName);
        }

        return $this->indexers[$collectionName];
    }

    /**
     * Get RetrieverService for a specific collection.
     */
    public function getRetriever(string $collectionName): RetrieverService
    {
        if (!isset($this->retrievers[$collectionName])) {
            $this->retrievers[$collectionName] = $this->createRetriever($collectionName);
        }

        return $this->retrievers[$collectionName];
    }

    /**
     * Get all collection names.
     *
     * @return string[]
     */
    public function getCollectionNames(): array
    {
        return $this->storeRegistry->getCollectionNames();
    }

    private function createIndexer(string $collectionName): IndexerService
    {
        $collectionConfig = $this->storeRegistry->getCollectionConfig($collectionName);
        $transformerConfig = $collectionConfig->getTransformer($this->config->transformer);

        $this->logger?->debug('Creating IndexerService', [
            'collection' => $collectionName,
            'chunk_size' => $transformerConfig->chunkSize,
            'overlap' => $transformerConfig->overlap,
        ]);

        return new IndexerService(
            store: $this->storeRegistry->getStore($collectionName),
            vectorizer: $this->getVectorizer(),
            metadataFactory: $this->metadataFactory,
            transformerConfig: $transformerConfig,
        );
    }

    private function createRetriever(string $collectionName): RetrieverService
    {
        $this->logger?->debug('Creating RetrieverService', [
            'collection' => $collectionName,
        ]);

        return new RetrieverService(
            store: $this->storeRegistry->getStore($collectionName),
            vectorizer: $this->getVectorizer(),
        );
    }

    private function getVectorizer(): VectorizerInterface
    {
        // Vectorizer is shared across all collections
        if ($this->vectorizer === null) {
            $this->vectorizer = $this->vectorizerFactory->create($this->config);
        }

        return $this->vectorizer;
    }
}
```

### 2. Updated IndexerService

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Service;

use Butschster\ContextGenerator\Rag\Config\TransformerConfig;
use Butschster\ContextGenerator\Rag\Document\DocumentType;
use Butschster\ContextGenerator\Rag\Document\MetadataFactory;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Transformer\TextSplitTransformer;
use Symfony\AI\Store\Document\VectorizerInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Uid\Uuid;

final readonly class IndexerService
{
    private TextSplitTransformer $transformer;

    public function __construct(
        private StoreInterface $store,
        private VectorizerInterface $vectorizer,
        private MetadataFactory $metadataFactory,
        TransformerConfig $transformerConfig,
    ) {
        $this->transformer = new TextSplitTransformer(
            chunkSize: $transformerConfig->chunkSize,
            overlap: $transformerConfig->overlap,
        );
    }

    // ... rest of methods unchanged
}
```

### 3. Updated RagStoreHandler

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\MCP\Tools\RagStore;

use Butschster\ContextGenerator\Rag\Document\DocumentType;
use Butschster\ContextGenerator\Rag\MCP\Tools\RagStore\Dto\RagStoreRequest;
use Butschster\ContextGenerator\Rag\Service\ServiceFactory;

final readonly class RagStoreHandler
{
    public function __construct(
        private ServiceFactory $serviceFactory,
        private string $collectionName = 'default',
    ) {}

    public function handle(RagStoreRequest $request): string
    {
        if (\trim($request->content) === '') {
            throw new \InvalidArgumentException('Content cannot be empty');
        }

        $type = DocumentType::tryFrom($request->type) ?? DocumentType::General;
        $indexer = $this->serviceFactory->getIndexer($this->collectionName);

        $result = $indexer->index(
            content: $request->content,
            type: $type,
            sourcePath: $request->sourcePath,
            tags: $request->getParsedTags(),
        );

        return \sprintf(
            "Stored in knowledge base [%s].\nType: %s | Chunks: %d | Time: %.2fms",
            $this->collectionName,
            $type->value,
            $result->chunksCreated,
            $result->processingTimeMs,
        );
    }

    /**
     * Create handler for specific collection.
     */
    public function withCollection(string $collectionName): self
    {
        return new self($this->serviceFactory, $collectionName);
    }
}
```

### 4. Updated RagSearchHandler

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\MCP\Tools\RagSearch;

use Butschster\ContextGenerator\Rag\Document\DocumentType;
use Butschster\ContextGenerator\Rag\MCP\Tools\RagSearch\Dto\RagSearchRequest;
use Butschster\ContextGenerator\Rag\Service\ServiceFactory;

final readonly class RagSearchHandler
{
    public function __construct(
        private ServiceFactory $serviceFactory,
        private string $collectionName = 'default',
    ) {}

    public function handle(RagSearchRequest $request): string
    {
        if (\trim($request->query) === '') {
            throw new \InvalidArgumentException('Query cannot be empty');
        }

        $type = $request->type !== null ? DocumentType::tryFrom($request->type) : null;
        $retriever = $this->serviceFactory->getRetriever($this->collectionName);

        $results = $retriever->search(
            query: $request->query,
            limit: $request->limit,
            type: $type,
            sourcePath: $request->sourcePath,
        );

        if ($results === []) {
            return \sprintf('No results found for "%s" in [%s]', $request->query, $this->collectionName);
        }

        $output = [\sprintf('Found %d results for "%s" in [%s]', \count($results), $request->query, $this->collectionName), ''];

        foreach ($results as $i => $item) {
            $output[] = \sprintf('--- Result %d ---', $i + 1);
            $output[] = $item->format();
            $output[] = '';
        }

        return \implode("\n", $output);
    }

    /**
     * Create handler for specific collection.
     */
    public function withCollection(string $collectionName): self
    {
        return new self($this->serviceFactory, $collectionName);
    }
}
```

### 5. Updated RagBootloader

Add to `RagBootloader::defineSingletons()`:

```php
// Service factory for multi-collection support
$container->bindSingleton(
    ServiceFactory::class,
    static function (
        RagRegistryInterface $ragRegistry,
        StoreRegistryInterface $storeRegistry,
        VectorizerFactory $vectorizerFactory,
        MetadataFactory $metadataFactory,
        LoggerInterface $logger,
    ) {
        return new ServiceFactory(
            config: $ragRegistry->getConfig(),
            storeRegistry: $storeRegistry,
            vectorizerFactory: $vectorizerFactory,
            metadataFactory: $metadataFactory,
            logger: $logger,
        );
    },
);

// Legacy IndexerService binding - uses default collection
$container->bindSingleton(
    IndexerService::class,
    static function (ServiceFactory $factory) {
        $collectionNames = $factory->getCollectionNames();
        if (empty($collectionNames)) {
            throw new \RuntimeException('No RAG collections configured');
        }
        return $factory->getIndexer($collectionNames[0]);
    },
);

// Legacy RetrieverService binding - uses default collection
$container->bindSingleton(
    RetrieverService::class,
    static function (ServiceFactory $factory) {
        $collectionNames = $factory->getCollectionNames();
        if (empty($collectionNames)) {
            throw new \RuntimeException('No RAG collections configured');
        }
        return $factory->getRetriever($collectionNames[0]);
    },
);
```

## Definition of Done

- [ ] `ServiceFactory` created with `getIndexer()` and `getRetriever()` methods
- [ ] Services are lazily created and cached per collection
- [ ] `IndexerService` accepts `TransformerConfig` instead of full `RagConfig`
- [ ] `RagStoreHandler` uses `ServiceFactory` with configurable collection
- [ ] `RagSearchHandler` uses `ServiceFactory` with configurable collection
- [ ] Both handlers have `withCollection()` method for creating collection-specific instances
- [ ] `ServiceFactory` registered in container via `RagBootloader`
- [ ] Legacy service bindings use default/first collection
- [ ] Unit tests verify per-collection service creation
- [ ] Existing RAG tools continue to work with legacy config

## Dependencies

**Requires**: Stage 1 (Configuration), Stage 2 (Store Registry)
**Enables**: Stage 5 (Dynamic Tools), Stage 6 (CLI Commands)
