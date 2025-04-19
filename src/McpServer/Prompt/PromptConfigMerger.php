<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Config\Import\Merger\AbstractConfigMerger;
use Butschster\ContextGenerator\Config\Import\Source\ImportedConfig;

#[LoggerPrefix(prefix: 'prompt-merger')]
final readonly class PromptConfigMerger extends AbstractConfigMerger
{
    public function getConfigKey(): string
    {
        return 'prompts';
    }

    protected function performMerge(array $mainSection, array $importedSection, ImportedConfig $importedConfig): array
    {
        // Index main prompts by ID for efficient lookups
        $indexedPrompts = [];
        foreach ($mainSection as $prompt) {
            if (!isset($prompt['id'])) {
                continue;
            }
            $indexedPrompts[$prompt['id']] = $prompt;
        }

        // Process each imported prompt
        foreach ($importedSection as $prompt) {
            if (!isset($prompt['id'])) {
                $this->logger->warning('Skipping prompt without ID', [
                    'prompt' => $prompt,
                    'path' => $importedConfig->path,
                ]);
                continue;
            }

            $promptId = $prompt['id'];
            $indexedPrompts[$promptId] = $prompt;

            $this->logger->debug('Merged prompt', [
                'id' => $promptId,
                'path' => $importedConfig->path,
            ]);
        }

        // Convert back to numerically indexed array
        return \array_values($indexedPrompts);
    }
}
