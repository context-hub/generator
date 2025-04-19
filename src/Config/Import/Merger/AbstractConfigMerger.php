<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import\Merger;

use Butschster\ContextGenerator\Config\Import\Source\ImportedConfig;
use Psr\Log\LoggerInterface;

abstract readonly class AbstractConfigMerger implements ConfigMergerInterface
{
    public function __construct(
        protected LoggerInterface $logger,
    ) {}

    public function merge(array $mainConfig, ImportedConfig $importedConfig): array
    {
        // Skip if this merger doesn't support the configuration
        if (!$this->supports($importedConfig)) {
            return $mainConfig;
        }

        $configKey = $this->getConfigKey();

        // Skip if the imported configuration doesn't contain this key
        if (!isset($importedConfig[$configKey]) || !\is_array($importedConfig[$configKey])) {
            return $mainConfig;
        }

        // Initialize the config section if it doesn't exist
        if (!isset($mainConfig[$configKey]) || !\is_array($mainConfig[$configKey])) {
            $mainConfig[$configKey] = [];
        }

        // Perform the actual merge
        $mainConfig[$configKey] = $this->performMerge(
            $mainConfig[$configKey],
            $importedConfig[$configKey],
            $importedConfig,
        );

        return $mainConfig;
    }

    public function supports(ImportedConfig $config): bool
    {
        $configKey = $this->getConfigKey();
        return isset($config[$configKey]) && \is_array($config[$configKey]);
    }

    /**
     * Perform the actual merge of configurations.
     * This method should be implemented by specific merger implementations.
     */
    abstract protected function performMerge(
        array $mainSection,
        array $importedSection,
        ImportedConfig $importedConfig,
    ): array;
}
