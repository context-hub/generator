<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\ConfigLoader;

use Butschster\ContextGenerator\ConfigLoader\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\ConfigLoader\Parser\ConfigParserInterface;
use Butschster\ContextGenerator\ConfigLoader\Reader\ReaderInterface;
use Butschster\ContextGenerator\ConfigLoader\Registry\DocumentRegistry;
use Psr\Log\LoggerInterface;

/**
 * Configuration loader that uses readers and parsers
 */
final readonly class ConfigLoader implements ConfigLoaderInterface
{
    public function __construct(
        private string $configPath,
        private ReaderInterface $reader,
        private ConfigParserInterface $parser,
        private string $rootPath,
        private ?LoggerInterface $logger = null,
    ) {}

    public function load(): DocumentRegistry
    {
        $this->logger?->info('Loading documents from config file', [
            'configFile' => $this->configPath,
            'readerType' => $this->reader::class,
        ]);

        try {
            // Read configuration using the appropriate reader
            $config = $this->reader->read($this->configPath);

            // Parse configuration with the config parser
            $this->logger?->debug('Parsing configuration with config parser');
            $configRegistry = $this->parser->parse($config);

            // Get the DocumentRegistry from the ConfigRegistry
            if (!$configRegistry->has('documents')) {
                $errorMessage = 'No documents found in configuration';
                $this->logger?->error($errorMessage);
                throw new ConfigLoaderException($errorMessage);
            }

            $documentRegistry = $configRegistry->get('documents', DocumentRegistry::class);
            $documentsCount = \count($documentRegistry->getItems());

            $this->logger?->info('Documents loaded successfully', [
                'documentsCount' => $documentsCount,
            ]);

            return $documentRegistry;
        } catch (\Throwable $e) {
            // Wrap exceptions in a ConfigLoaderException
            throw new ConfigLoaderException(
                \sprintf('Failed to load configuration from %s: %s', $this->configPath, $e->getMessage()),
                previous: $e,
            );
        }
    }

    public function isSupported(): bool
    {
        return $this->reader->supports($this->configPath);
    }
}
