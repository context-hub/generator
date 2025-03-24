<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\ConfigLoader\Import;

use Butschster\ContextGenerator\ConfigLoader\ConfigLoaderFactory;
use Butschster\ContextGenerator\ConfigLoader\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\FilesInterface;
use Psr\Log\LoggerInterface;

/**
 * Resolves import directives in configuration files
 */
final readonly class ImportResolver
{
    public function __construct(
        private FilesInterface $files,
        private ConfigLoaderFactory $loaderFactory,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Process imports in a configuration
     *
     * @param array<mixed> $config The configuration containing imports
     * @param string $basePath The base path for resolving relative paths
     * @param array<string> $parsedImports Already processed import paths to avoid duplicates
     * @param CircularImportDetector $detector Circular import detector
     * @return array<mixed> The merged configuration with imports processed
     */
    public function resolveImports(
        array $config,
        string $basePath,
        array &$parsedImports = [],
        ?CircularImportDetector $detector = null,
    ): array {
        // Initialize circular import detector if not provided
        $detector ??= new CircularImportDetector();

        // If no imports, return the original config
        if (!isset($config['import']) || empty($config['import'])) {
            return $config;
        }

        $imports = $config['import'];
        if (!\is_array($imports)) {
            throw new ConfigLoaderException('The "import" property must be an array');
        }

        // Process each import
        $importedConfigs = [];
        foreach ($imports as $importConfig) {
            $importCfg = ImportConfig::fromArray($importConfig, $basePath);

            // Skip if already processed
            if (\in_array($importCfg->absolutePath, $parsedImports, true)) {
                $this->logger?->debug('Skipping already processed import', [
                    'path' => $importCfg->path,
                    'absolutePath' => $importCfg->absolutePath,
                ]);
                continue;
            }

            // Check for circular imports
            $detector->beginProcessing($importCfg->absolutePath);

            try {
                // Load the import configuration
                $importedConfig = $this->loadImportConfig($importCfg);

                // Recursively process nested imports
                $dirname = \dirname($importCfg->absolutePath);
                $importedConfig = $this->resolveImports(
                    $importedConfig,
                    $dirname,
                    $parsedImports,
                    $detector,
                );

                // Apply path prefix if specified
                $importedConfig = $this->applyPathPrefix($importedConfig, \dirname((string) $importConfig['path']));

                // Store for later merging
                $importedConfigs[] = $importedConfig;

                // Mark as processed
                $parsedImports[] = $importCfg->absolutePath;

                $this->logger?->debug('Successfully processed import', [
                    'path' => $importCfg->path,
                    'absolutePath' => $importCfg->absolutePath,
                ]);
            } finally {
                // Always end processing to maintain stack integrity
                $detector->endProcessing($importCfg->absolutePath);
            }
        }

        // Remove the import directive from the original config
        unset($config['import']);

        // Merge all configurations
        return $this->mergeConfigurations([$config, ...$importedConfigs]);
    }

    /**
     * Load an import configuration
     */
    private function loadImportConfig(ImportConfig $importConfig): array
    {
        if (!$this->files->exists($importConfig->absolutePath)) {
            throw new ConfigLoaderException(
                \sprintf('Import file not found: %s', $importConfig->absolutePath),
            );
        }

        try {
            $loader = $this->loaderFactory->createForFile($importConfig->absolutePath);

            if (!$loader->isSupported()) {
                throw new ConfigLoaderException(
                    \sprintf('Unsupported import file format: %s', $importConfig->absolutePath),
                );
            }

            // Load the raw configuration (before registry conversion)
            return $loader->loadRawConfig();
        } catch (\Throwable $e) {
            throw new ConfigLoaderException(
                \sprintf('Failed to load import file %s: %s', $importConfig->absolutePath, $e->getMessage()),
                previous: $e,
            );
        }
    }

    /**
     * Apply path prefix to relevant source paths
     */
    private function applyPathPrefix(array $config, string $pathPrefix): array
    {
        // Apply to documents array
        if (isset($config['documents']) && \is_array($config['documents'])) {
            foreach ($config['documents'] as &$document) {
                if (isset($document['sources']) && \is_array($document['sources'])) {
                    foreach ($document['sources'] as &$source) {
                        $source = $this->applyPathPrefixToSource($source, $pathPrefix);
                    }
                }
            }
        }

        // Also consider global pathPrefix
        if (isset($config['pathPrefix'])) {
            $config['pathPrefix'] = $this->combinePaths($pathPrefix, $config['pathPrefix']);
        } else {
            $config['pathPrefix'] = $pathPrefix;
        }

        return $config;
    }

    /**
     * Apply path prefix to a source configuration
     */
    private function applyPathPrefixToSource(array $source, string $prefix): array
    {
        // Handle sourcePaths property
        if (isset($source['sourcePaths'])) {
            $source['sourcePaths'] = $this->applyPrefixToPaths($source['sourcePaths'], $prefix);
        }

        // Handle repository property (for git_diff source)
        if (isset($source['repository']) && $source['type'] === 'git_diff') {
            // Only prefix if it's a relative path
            if (!\str_starts_with($source['repository'], '/')) {
                $source['repository'] = $this->combinePaths($prefix, $source['repository']);
            }
        }

        // Handle composerPath property (for composer source)
        if (isset($source['composerPath']) && $source['type'] === 'composer') {
            if (!\str_starts_with($source['composerPath'], '/')) {
                $source['composerPath'] = $this->combinePaths($prefix, $source['composerPath']);
            }
        }

        return $source;
    }

    /**
     * Apply prefix to path(s)
     */
    private function applyPrefixToPaths(mixed $paths, string $prefix): mixed
    {
        if (\is_string($paths)) {
            // Skip absolute paths
            if (\str_starts_with($paths, '/')) {
                return $paths;
            }
            return $this->combinePaths($prefix, $paths);
        }

        if (\is_array($paths)) {
            $result = [];
            foreach ($paths as $path) {
                $result[] = $this->applyPrefixToPaths($path, $prefix);
            }
            return $result;
        }

        // If it's not a string or array, return as is
        return $paths;
    }

    /**
     * Combine two paths with a separator
     */
    private function combinePaths(string $prefix, string $path): string
    {
        return \rtrim($prefix, '/') . '/' . \ltrim($path, '/');
    }

    /**
     * Merge multiple configurations
     */
    private function mergeConfigurations(array $configs): array
    {
        $result = [];

        foreach ($configs as $config) {
            // Special handling for documents array - append instead of replace
            if (isset($config['documents']) && \is_array($config['documents'])) {
                $result['documents'] = \array_merge(
                    $result['documents'] ?? [],
                    $config['documents'],
                );
                unset($config['documents']);
            }

            // Merge the rest of the configuration
            $result = \array_merge($result, $config);
        }

        return $result;
    }
}
