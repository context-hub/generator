<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import\Source;

use Butschster\ContextGenerator\Config\Import\Source\Config\SourceConfigInterface;
use Butschster\ContextGenerator\Config\Import\Source\Local\LocalSourceConfig;
use Butschster\ContextGenerator\Config\Reader\ReaderInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Abstract base class for import sources with common functionality
 */
abstract class AbstractImportSource implements ImportSourceInterface
{
    public function __construct(
        protected readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * Read and parse a configuration file using the appropriate reader
     */
    protected function readConfig(string $path, ReaderInterface $reader): array
    {
        $this->logger->debug('Reading configuration file', [
            'path' => $path,
            'source' => $this->getName(),
        ]);

        if (!$reader->supports($path)) {
            $this->logger->warning('Reader does not support file', [
                'path' => $path,
                'source' => $this->getName(),
            ]);

            throw new Exception\ImportSourceException(
                \sprintf('Unsupported configuration file format: %s', $path),
            );
        }

        return $reader->read($path);
    }

    /**
     * Process selective imports if specified in the config
     */
    protected function processSelectiveImports(array $config, SourceConfigInterface $sourceConfig): array
    {
        $config = $this->filterConfigSections($config);

        if (!isset($config['documents']) || !$sourceConfig instanceof LocalSourceConfig) {
            return $config;
        }

        // If no specific docs are requested, return the full config
        $selectiveDocs = $sourceConfig->getSelectiveDocuments();
        if (empty($selectiveDocs)) {
            return $this->filterConfigSections($config);
        }

        $this->logger->debug('Processing selective imports', [
            'path' => $sourceConfig->getPath(),
            'docs' => $selectiveDocs,
            'source' => $this->getName(),
        ]);

        // For selective imports, we only include specific documents
        if (isset($config['documents']) && \is_array($config['documents'])) {
            $filteredDocuments = [];

            foreach ($config['documents'] as $document) {
                if (isset($document['outputPath'])) {
                    // Check if this document's path is in the requested docs
                    $outputPath = $document['outputPath'];
                    foreach ($selectiveDocs as $requestedDoc) {
                        // Simple wildcard matching
                        $pattern = $this->wildcardToRegex($requestedDoc);
                        if (\preg_match($pattern, (string) $outputPath)) {
                            $filteredDocuments[] = $document;
                            break;
                        }
                    }
                }
            }

            // Replace the original documents with the filtered ones
            $config['documents'] = $filteredDocuments;

            $this->logger->debug('Selective import processed', [
                'totalDocuments' => \count($filteredDocuments),
            ]);
        }

        return $this->filterConfigSections($config);
    }

    /**
     * Convert a wildcard pattern to a regex pattern
     */
    protected function wildcardToRegex(string $pattern): string
    {
        $pattern = \preg_quote($pattern, '/');
        $pattern = \str_replace('\*', '.*', $pattern);
        $pattern = \str_replace('\?', '.', $pattern);
        return '/^' . $pattern . '$/';
    }

    private function filterConfigSections(array $config): array
    {
        if ($this->allowedSections() === []) {
            return $config;
        }

        $newConfig = [];
        foreach ($this->allowedSections() as $section) {
            if (isset($config[$section])) {
                $newConfig[$section] = $config[$section];
            }
        }

        return $newConfig;
    }
}
