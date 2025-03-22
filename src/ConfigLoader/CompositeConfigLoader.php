<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\ConfigLoader;

use Butschster\ContextGenerator\ConfigLoader\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\ConfigLoader\Registry\DocumentRegistry;
use Psr\Log\LoggerInterface;

/**
 * Combines multiple config loaders into one
 */
final readonly class CompositeConfigLoader implements ConfigLoaderInterface
{
    public function __construct(
        /** @var array<ConfigLoaderInterface> */
        private array $loaders,
        private ?LoggerInterface $logger = null,
    ) {}

    public function load(): DocumentRegistry
    {
        $this->logger?->debug('Trying to load with composite loader', [
            'loaderCount' => \count($this->loaders),
        ]);

        $registry = new DocumentRegistry();
        $atLeastOneLoaded = false;

        foreach ($this->loaders as $loader) {
            if (!$loader->isSupported()) {
                continue;
            }

            try {
                $loadedRegistry = $loader->load();
                $atLeastOneLoaded = true;

                foreach ($loadedRegistry->getItems() as $document) {
                    $registry->register($document);
                }

                $this->logger?->debug('Successfully loaded with a loader', [
                    'loaderClass' => $loader::class,
                    'documentCount' => \count($loadedRegistry->getItems()),
                ]);
            } catch (\Throwable $e) {
                $this->logger?->warning('Failed to load with a loader', [
                    'loaderClass' => $loader::class,
                    'error' => $e->getMessage(),
                ]);
                // Continue with other loaders
            }
        }

        if (!$atLeastOneLoaded) {
            throw new ConfigLoaderException('No configuration could be loaded with any available loader');
        }

        return $registry;
    }

    public function isSupported(): bool
    {
        foreach ($this->loaders as $loader) {
            if ($loader->isSupported()) {
                return true;
            }
        }

        return false;
    }
}
