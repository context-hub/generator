<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import;

use Butschster\ContextGenerator\Config\Parser\ConfigParserPluginInterface;
use Butschster\ContextGenerator\Config\Registry\RegistryInterface;
use Psr\Log\LoggerInterface;

/**
 * Plugin for processing import directives in configuration
 */
final readonly class ImportParserPlugin implements ConfigParserPluginInterface
{
    public function __construct(
        private ImportResolver $importResolver,
        private ?LoggerInterface $logger = null,
    ) {}

    public function getConfigKey(): string
    {
        return 'import';
    }

    public function parse(array $config, string $rootPath): ?RegistryInterface
    {
        // We don't create a registry here, we just update the config
        // via the updateConfig method
        return null;
    }

    public function supports(array $config): bool
    {
        return isset($config['import']) && \is_array($config['import']);
    }

    public function updateConfig(array $config, string $rootPath): array
    {
        // If no imports, return the original config
        if (!$this->supports($config)) {
            return $config;
        }

        $this->logger?->debug('Processing imports', [
            'rootPath' => $rootPath,
            'importCount' => \count($config['import']),
        ]);

        // Process imports and return the merged configuration
        $processedConfig = $this->importResolver->resolveImports($config, $rootPath);

        $this->logger?->debug('Imports processed successfully');

        return $processedConfig;
    }
}
