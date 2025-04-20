<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Config\Import\Merger\AbstractConfigMerger;
use Butschster\ContextGenerator\Config\Import\Source\ImportedConfig;
use Butschster\ContextGenerator\McpServer\Prompt\Filter\PromptFilterFactory;
use Psr\Log\LoggerInterface;

#[LoggerPrefix(prefix: 'prompt-merger')]
final readonly class PromptConfigMerger extends AbstractConfigMerger
{
    public function __construct(
        LoggerInterface $logger,
        private PromptFilterFactory $filterFactory = new PromptFilterFactory(),
    ) {
        parent::__construct($logger);
    }

    public function getConfigKey(): string
    {
        return 'prompts';
    }

    protected function performMerge(array $mainSection, array $importedSection, ImportedConfig $importedConfig): array
    {
        // Create filter if source config has filter configuration
        $filter = null;
        $sourceConfig = $importedConfig->sourceConfig;
        $filterConfig = $sourceConfig->getFilter();

        if ($filterConfig !== null && !$filterConfig->isEmpty()) {
            $filter = $this->filterFactory->createFromConfig($filterConfig->getConfig());

            if ($filter !== null) {
                $this->logger->debug('Created prompt filter for import', [
                    'path' => $importedConfig->path,
                    'filterConfig' => $filterConfig->getConfig(),
                ]);
            }
        }

        // Index main prompts by ID for efficient lookups
        $indexedPrompts = [];
        foreach ($mainSection as $prompt) {
            if (!isset($prompt['id'])) {
                continue;
            }
            $indexedPrompts[$prompt['id']] = $prompt;
        }

        // Process each imported prompt
        $importedCount = 0;
        $filteredCount = 0;

        foreach ($importedSection as $prompt) {
            if (!isset($prompt['id'])) {
                $this->logger->warning('Skipping prompt without ID', [
                    'prompt' => $prompt,
                    'path' => $importedConfig->path,
                ]);
                continue;
            }

            // Apply filter if it exists
            if ($filter !== null && !$filter->shouldInclude($prompt)) {
                $this->logger->debug('Filtered out prompt', [
                    'id' => $prompt['id'],
                    'path' => $importedConfig->path,
                ]);
                $filteredCount++;
                continue;
            }

            $promptId = $prompt['id'];
            $indexedPrompts[$promptId] = $prompt;
            $importedCount++;

            $this->logger->debug('Merged prompt', [
                'id' => $promptId,
                'path' => $importedConfig->path,
            ]);
        }

        if ($filter !== null) {
            $this->logger->info('Import filtering results', [
                'path' => $importedConfig->path,
                'imported' => $importedCount,
                'filtered' => $filteredCount,
                'total' => \count($importedSection),
            ]);
        }

        // Convert back to numerically indexed array
        return \array_values($indexedPrompts);
    }
}
