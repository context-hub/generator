<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import\Merger;

use Butschster\ContextGenerator\Config\Import\Source\ImportedConfig;

interface ConfigMergerProviderInterface
{
    /**
     * Merge an imported configuration with the main configuration.
     *
     * @param array<string, mixed> $mainConfig The main configuration to merge into
     * @param ImportedConfig ...$configs The imported configurations to merge from
     * @return array<string, mixed> The merged configuration
     */
    public function mergeConfigurations(array $mainConfig, ImportedConfig ...$configs): array;
}
