<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Store;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Rag\Config\CollectionConfig;
use Butschster\ContextGenerator\Rag\Config\RagConfig;
use Butschster\ContextGenerator\Rag\RagRegistryInterface;
use Psr\Log\LoggerInterface;
use Symfony\AI\Store\StoreInterface;

/**
 * Registry for managing multiple store instances.
 *
 * Stores are created lazily on first access and cached for reuse.
 */
final class StoreRegistry implements StoreRegistryInterface
{
    /** @var array<string, StoreInterface> */
    private array $stores = [];

    public function __construct(
        private readonly RagRegistryInterface $ragRegistry,
        private readonly StoreFactory $factory,
        #[LoggerPrefix(prefix: 'store-registry')]
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function getStore(string $collectionName): StoreInterface
    {
        if (!$this->hasCollection($collectionName)) {
            throw new \InvalidArgumentException(
                \sprintf('Collection "%s" not found in RAG configuration', $collectionName),
            );
        }

        if (!isset($this->stores[$collectionName])) {
            $this->stores[$collectionName] = $this->createStore($collectionName);
        }

        return $this->stores[$collectionName];
    }

    public function hasCollection(string $collectionName): bool
    {
        return $this->getConfig()->hasCollection($collectionName);
    }

    public function getCollectionConfig(string $collectionName): CollectionConfig
    {
        return $this->getConfig()->getCollection($collectionName);
    }

    public function getCollectionNames(): array
    {
        return $this->getConfig()->getCollectionNames();
    }

    private function getConfig(): RagConfig
    {
        return $this->ragRegistry->getConfig();
    }

    private function createStore(string $collectionName): StoreInterface
    {
        $config = $this->getConfig();
        $collectionConfig = $config->getCollection($collectionName);
        $serverConfig = $config->getServer($collectionConfig->server);

        $this->logger?->debug('Creating store for collection', [
            'collection' => $collectionName,
            'server' => $collectionConfig->server,
            'driver' => $serverConfig->driver,
        ]);

        return $this->factory->createForCollection($serverConfig, $collectionConfig);
    }
}
