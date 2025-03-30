<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Loader;

use Butschster\ContextGenerator\Config\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\Config\Registry\RegistryInterface;
use Butschster\ContextGenerator\Document\DocumentRegistry;
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

    public function load(): RegistryInterface
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
            throw new ConfigLoaderException('There are no loaders that can load the configuration in the given path');
        }

        return $registry;
    }

    public function loadRawConfig(): array
    {
        $this->logger?->debug('Trying to load raw config with composite loader', [
            'loaderCount' => \count($this->loaders),
        ]);

        foreach ($this->loaders as $loader) {
            if (!$loader->isSupported()) {
                continue;
            }

            try {
                $rawConfig = $loader->loadRawConfig();

                $this->logger?->debug('Successfully loaded raw config', [
                    'loaderClass' => $loader::class,
                ]);

                return $rawConfig;
            } catch (\Throwable $e) {
                $this->logger?->warning('Failed to load raw config', [
                    'loaderClass' => $loader::class,
                    'error' => $e->getMessage(),
                ]);
                // Continue with other loaders
            }
        }

        throw new ConfigLoaderException('There are no loaders that can load the raw configuration');
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
