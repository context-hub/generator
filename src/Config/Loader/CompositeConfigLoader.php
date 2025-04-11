<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Loader;

use Butschster\ContextGenerator\Config\Registry\ConfigRegistry;
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

    public function load(): ConfigRegistry
    {
        $this->logger?->debug('Trying to load with composite loader', [
            'loaderCount' => \count($this->loaders),
        ]);

        $compositeRegistry = new ConfigRegistry();

        foreach ($this->loaders as $loader) {
            if (!$loader->isSupported()) {
                continue;
            }

            try {
                $registry = $loader->load();

                // Merge all registry types from this loader
                foreach ($registry->all() as $type => $typeRegistry) {
                    if (!$compositeRegistry->has($type)) {
                        $compositeRegistry->register($typeRegistry);
                        $this->logger?->debug('Registered registry type', [
                            'type' => $type,
                        ]);
                    } else {
                        $this->logger?->debug('Registry type already exists', [
                            'type' => $type,
                        ]);
                    }
                }

                $this->logger?->debug('Successfully loaded with a loader', [
                    'loaderClass' => $loader::class,
                    'registryTypes' => \array_keys($registry->all()),
                ]);
            } catch (\Throwable $e) {
                $this->logger?->warning('Failed to load with a loader', [
                    'loaderClass' => $loader::class,
                    'error' => $e->getMessage(),
                ]);
                // Continue with other loaders
            }
        }

        return $compositeRegistry;
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

        return [];
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
