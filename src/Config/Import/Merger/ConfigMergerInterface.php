<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import\Merger;

use Butschster\ContextGenerator\Config\Import\Source\ImportedConfig;

/**
 * Interface for all configuration mergers.
 */
interface ConfigMergerInterface
{
    /**
     * Get the configuration key that this merger handles.
     */
    public function getConfigKey(): string;

    /**
     * Merge an imported configuration with the main configuration.
     *
     * @param array<string, mixed> $mainConfig The main configuration to merge into
     * @param ImportedConfig $importedConfig The imported configuration to merge from
     * @return array<string, mixed> The merged configuration
     */
    public function merge(array $mainConfig, ImportedConfig $importedConfig): array;

    /**
     * Check if this merger supports the given configuration.
     *
     * @param ImportedConfig $config The configuration to check
     * @return bool True if this merger supports the configuration
     */
    public function supports(ImportedConfig $config): bool;
}
