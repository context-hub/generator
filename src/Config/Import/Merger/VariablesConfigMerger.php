<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import\Merger;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Config\Import\Source\ImportedConfig;

#[LoggerPrefix(prefix: 'variables-merger')]
final readonly class VariablesConfigMerger extends AbstractConfigMerger
{
    public function getConfigKey(): string
    {
        return 'variables';
    }

    protected function performMerge(array $mainSection, array $importedSection, ImportedConfig $importedConfig): array
    {
        // Merge the variables from the imported config into the main config
        return [...$importedSection, ...$mainSection];
    }
}
