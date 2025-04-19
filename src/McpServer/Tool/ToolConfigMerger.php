<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Config\Import\Merger\AbstractConfigMerger;
use Butschster\ContextGenerator\Config\Import\Source\ImportedConfig;

#[LoggerPrefix(prefix: 'tool-merger')]
final readonly class ToolConfigMerger extends AbstractConfigMerger
{
    public function getConfigKey(): string
    {
        return 'tools';
    }

    protected function performMerge(array $mainSection, array $importedSection, ImportedConfig $importedConfig): array
    {
        // Index main tools by ID for efficient lookups
        $indexedTools = [];
        foreach ($mainSection as $tool) {
            if (!isset($tool['id'])) {
                continue;
            }
            $indexedTools[$tool['id']] = $tool;
        }

        // Process each imported tool
        foreach ($importedSection as $tool) {
            if (!isset($tool['id'])) {
                $this->logger->warning('Skipping tool without ID', [
                    'tool' => $tool,
                    'path' => $importedConfig->path,
                ]);
                continue;
            }

            $toolId = $tool['id'];

            // Special handling for working directory
            $workingDir = $tool['workingDir'] ?? '.';
            if ($importedConfig->isLocal && $workingDir === '.') {
                $tool['workingDir'] = \dirname($importedConfig->path);
                $this->logger->debug('Updated tool working directory', [
                    'id' => $toolId,
                    'workingDir' => $tool['workingDir'],
                ]);
            }

            $indexedTools[$toolId] = $tool;

            $this->logger->debug('Merged tool', [
                'id' => $toolId,
                'path' => $importedConfig->path,
            ]);
        }

        // Convert back to numerically indexed array
        return \array_values($indexedTools);
    }
}
