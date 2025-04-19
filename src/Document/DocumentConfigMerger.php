<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Document;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Config\Import\Merger\AbstractConfigMerger;
use Butschster\ContextGenerator\Config\Import\Source\ImportedConfig;

#[LoggerPrefix(prefix: 'document-merger')]
final readonly class DocumentConfigMerger extends AbstractConfigMerger
{
    public function getConfigKey(): string
    {
        return 'documents';
    }

    protected function performMerge(array $mainSection, array $importedSection, ImportedConfig $importedConfig): array
    {
        // Index main documents by output path for efficient lookups
        $indexedDocuments = [];
        foreach ($mainSection as $document) {
            if (!isset($document['outputPath'])) {
                continue;
            }
            $indexedDocuments[$document['outputPath']] = $document;
        }

        // Process each imported document
        foreach ($importedSection as $document) {
            if (!isset($document['outputPath'])) {
                $this->logger->warning('Skipping document without outputPath', [
                    'document' => $document,
                    'path' => $importedConfig->path,
                ]);
                continue;
            }

            $outputPath = $document['outputPath'];
            $indexedDocuments[$outputPath] = $document;

            $this->logger->debug('Merged document', [
                'outputPath' => $outputPath,
                'path' => $importedConfig->path,
            ]);
        }

        // Convert back to numerically indexed array
        return \array_values($indexedDocuments);
    }
}
