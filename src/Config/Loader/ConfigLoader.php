<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Loader;

use Butschster\ContextGenerator\Config\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\Config\Parser\ConfigParserInterface;
use Butschster\ContextGenerator\Config\Reader\ReaderInterface;
use Butschster\ContextGenerator\Config\Registry\ConfigRegistry;
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
        private ?LoggerInterface $logger = null,
    ) {}

    public function load(): ConfigRegistry
    {
        $this->logger?->info('Loading configuration from config file', [
            'configFile' => $this->configPath,
            'readerType' => $this->reader::class,
        ]);

        try {
            // Read configuration using the appropriate reader
            $config = $this->reader->read($this->configPath);

            // Parse configuration with the config parser
            $this->logger?->debug('Parsing configuration with config parser');
            $configRegistry = $this->parser->parse($config);

            // Log the available registry types
            $registryTypes = \array_keys($configRegistry->all());
            $this->logger?->info('Configuration loaded successfully', [
                'registryTypes' => $registryTypes,
            ]);

            return $configRegistry;
        } catch (\Throwable $e) {
            // Wrap exceptions in a ConfigLoaderException
            throw new ConfigLoaderException(
                \sprintf('Failed to load configuration from %s: %s', $this->configPath, $e->getMessage()),
                previous: $e,
            );
        }
    }

    /**
     * Load the raw configuration without processing into a registry
     */
    public function loadRawConfig(): array
    {
        $this->logger?->debug('Loading raw configuration', [
            'configFile' => $this->configPath,
        ]);

        try {
            // Read configuration using the appropriate reader
            return $this->reader->read($this->configPath);
        } catch (\Throwable $e) {
            // Wrap exceptions in a ConfigLoaderException
            throw new ConfigLoaderException(
                \sprintf('Failed to load raw configuration from %s: %s', $this->configPath, $e->getMessage()),
                previous: $e,
            );
        }
    }

    public function isSupported(): bool
    {
        return $this->reader->supports($this->configPath);
    }
}
