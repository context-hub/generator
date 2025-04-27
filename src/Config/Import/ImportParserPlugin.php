<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Config\Parser\ConfigParserPluginInterface;
use Butschster\ContextGenerator\Config\Registry\RegistryInterface;
use Psr\Log\LoggerInterface;

/**
 * Plugin for processing import directives in configuration
 */
final readonly class ImportParserPlugin implements ConfigParserPluginInterface
{
    private ImportRegistry $registry;

    public function __construct(
        private ImportResolver $importResolver,
        #[LoggerPrefix(prefix: 'import-parser')]
        private ?LoggerInterface $logger = null,
    ) {
        $this->registry = new ImportRegistry();
    }

    public function getConfigKey(): string
    {
        return 'import';
    }

    public function parse(array $config, string $rootPath): ?RegistryInterface
    {
        return $this->registry;
    }

    public function supports(array $config): bool
    {
        return true;
    }

    public function updateConfig(array $config, string $rootPath): array
    {
        // If no imports, return the original config
        if (!$this->supports($config)) {
            return $config;
        }

        if (!isset($config['import'])) {
            return $config;
        }

        if (!\is_array($config['import'])) {
            $this->logger?->warning('Invalid import configuration', [
                'config' => $config['import'],
            ]);

            return $config;
        }

        $this->logger?->debug('Processing imports', [
            'rootPath' => $rootPath,
            'importCount' => \count($config['import']),
        ]);

        // Process imports and return the merged configuration
        $processedConfig = $this->importResolver->resolveImports($config, $rootPath);

        foreach ($processedConfig->imports as $import) {
            $this->registry->register($import);
        }

        $this->logger?->debug('Imports processed successfully');

        return $processedConfig->config;
    }
}
