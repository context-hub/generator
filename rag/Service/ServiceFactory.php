<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Service;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Rag\Config\RagConfig;
use Butschster\ContextGenerator\Rag\Document\MetadataFactory;
use Butschster\ContextGenerator\Rag\RagRegistryInterface;
use Butschster\ContextGenerator\Rag\Store\StoreRegistryInterface;
use Butschster\ContextGenerator\Rag\Vectorizer\VectorizerFactory;
use Psr\Log\LoggerInterface;
use Symfony\AI\Store\Document\VectorizerInterface;

/**
 * Factory for creating collection-specific indexer and retriever services.
 *
 * Services are lazily created and cached for reuse.
 */
final class ServiceFactory
{
    private ?VectorizerInterface $vectorizer = null;

    /** @var array<string, IndexerService> */
    private array $indexers = [];

    /** @var array<string, RetrieverService> */
    private array $retrievers = [];

    public function __construct(
        private readonly RagRegistryInterface $ragRegistry,
        private readonly StoreRegistryInterface $storeRegistry,
        private readonly VectorizerFactory $vectorizerFactory,
        private readonly MetadataFactory $metadataFactory,
        #[LoggerPrefix(prefix: 'service-factory')]
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

    /**
     * Check if a collection exists.
     */
    public function hasCollection(string $collectionName): bool
    {
        return $this->storeRegistry->hasCollection($collectionName);
    }

    private function getConfig(): RagConfig
    {
        return $this->ragRegistry->getConfig();
    }

    private function createIndexer(string $collectionName): IndexerService
    {
        $collectionConfig = $this->storeRegistry->getCollectionConfig($collectionName);
        $transformerConfig = $collectionConfig->getTransformer($this->getConfig()->transformer);

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
        if ($this->vectorizer === null) {
            $this->vectorizer = $this->vectorizerFactory->create($this->getConfig());
        }

        return $this->vectorizer;
    }
}
