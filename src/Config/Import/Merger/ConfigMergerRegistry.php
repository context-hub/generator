<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import\Merger;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Config\Import\Source\ImportedConfig;
use Psr\Log\LoggerInterface;

/**
 * Registry for configuration mergers.
 */
#[LoggerPrefix(prefix: 'config-merger-registry')]
final class ConfigMergerRegistry implements ConfigMergerProviderInterface
{
    /**
     * @var array<string, ConfigMergerInterface> Mergers indexed by config key
     */
    private array $mergers = [];

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Register a merger in the registry.
     *
     * @param ConfigMergerInterface $merger The merger to register
     * @return self Fluent interface
     */
    public function register(ConfigMergerInterface $merger): self
    {
        $key = $merger->getConfigKey();
        $this->mergers[$key] = $merger;

        $this->logger->debug('Registered config merger', [
            'configKey' => $key,
            'class' => $merger::class,
        ]);

        return $this;
    }

    public function mergeConfigurations(array $mainConfig, ImportedConfig ...$configs): array
    {
        $result = $mainConfig;

        foreach ($configs as $importedConfig) {
            foreach ($this->mergers as $configKey => $merger) {
                // Apply each merger if it supports the configuration
                if ($merger->supports($importedConfig)) {
                    $result = $merger->merge($result, $importedConfig);

                    $this->logger->debug('Applied config merger', [
                        'configKey' => $configKey,
                        'path' => $importedConfig->path,
                    ]);
                }
            }
        }

        return $result;
    }
}
