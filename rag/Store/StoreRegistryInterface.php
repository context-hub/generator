<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Store;

use Butschster\ContextGenerator\Rag\Config\CollectionConfig;
use Symfony\AI\Store\StoreInterface;

/**
 * Registry for managing multiple store instances by collection name.
 */
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
