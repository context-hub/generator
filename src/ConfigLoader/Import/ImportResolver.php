<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\ConfigLoader\Import;

use Butschster\ContextGenerator\ConfigLoader\ConfigLoaderFactoryInterface;
use Butschster\ContextGenerator\ConfigLoader\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\Directories;
use Butschster\ContextGenerator\FilesInterface;
use Psr\Log\LoggerInterface;

/**
 * Resolves import directives in configuration files
 */
final readonly class ImportResolver
{
    private WildcardPathFinder $pathFinder;

    public function __construct(
        private Directories $dirs,
        private FilesInterface $files,
        private ConfigLoaderFactoryInterface $loaderFactory,
        private ?LoggerInterface $logger = null,
    ) {
        $this->pathFinder = new WildcardPathFinder($files, $logger);
    }

    /**
     * Process imports in a configuration
     * @throws \Throwable
     */
    public function resolveImports(
        array $config,
        string $basePath,
        array &$parsedImports = [],
        CircularImportDetectorInterface $detector = new CircularImportDetector(),
    ): array {
        // If no imports, return the original config
        if (empty($config['import'])) {
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

            // Handle wildcard paths
            if ($importCfg->hasWildcard) {
                $this->processWildcardImport(
                    $importCfg,
                    $basePath,
                    $parsedImports,
                    $detector,
                    $importedConfigs,
                    $importConfig,
                );
                continue;
            }

            // Skip if already processed
            if (\in_array($importCfg->absolutePath, $parsedImports, true)) {
                $this->logger?->debug('Skipping already processed import', [
                    'path' => $importCfg->path,
                    'absolutePath' => $importCfg->absolutePath,
                ]);
                continue;
            }

            // Process a single standard import
            $this->processSingleImport(
                $importCfg,
                $basePath,
                $parsedImports,
                $detector,
                $importedConfigs,
                $importConfig,
            );
        }

        // Remove the import directive from the original config
        unset($config['import']);

        // Merge all configurations
        return $this->mergeConfigurations([$config, ...$importedConfigs]);
    }

    /**
     * Process a wildcard import pattern
     */
    private function processWildcardImport(
        ImportConfig $importCfg,
        string $basePath,
        array &$parsedImports,
        CircularImportDetector $detector,
        array &$importedConfigs,
        array $importConfig,
    ): void {
        // Find all files that match the pattern
        $matchingPaths = $this->pathFinder->findMatchingPaths($importCfg->path, $basePath);

        if (empty($matchingPaths)) {
            $this->logger?->warning('No files match the wildcard pattern', [
                'pattern' => $importCfg->path,
                'basePath' => $basePath,
            ]);
            return;
        }

        $this->logger?->debug('Found files matching wildcard pattern', [
            'pattern' => $importCfg->path,
            'count' => \count($matchingPaths),
            'paths' => $matchingPaths,
        ]);

        // Process each matching file
        foreach ($matchingPaths as $matchingPath) {
            // Skip if already processed
            if (\in_array($matchingPath, $parsedImports, true)) {
                $this->logger?->debug('Skipping already processed wildcard match', [
                    'path' => $matchingPath,
                ]);
                continue;
            }

            // Create a single import config for this match
            $singleImportCfg = new ImportConfig(
                path: \ltrim(\str_replace($this->dirs->rootPath, '', $matchingPath), '/'), // Use the actual file path
                absolutePath: $matchingPath, // The path finder returns absolute paths
                pathPrefix: $importCfg->pathPrefix,
                hasWildcard: false,
            );

            // Process it using the standard import logic
            $this->processSingleImport(
                $singleImportCfg,
                \dirname($matchingPath), // Base path is the directory of the matched file
                $parsedImports,
                $detector,
                $importedConfigs,
                $importConfig,
            );
        }
    }

    /**
     * Process a single non-wildcard import
     */
    private function processSingleImport(
        ImportConfig $importCfg,
        string $basePath,
        array &$parsedImports,
        CircularImportDetectorInterface $detector,
        array &$importedConfigs,
        array $importConfig,
    ): void {
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

            // Apply path prefix for output document paths if specified
            if ($importCfg->pathPrefix !== null) {
                $importedConfig = $this->applyPathPrefix($importedConfig, $importCfg->pathPrefix);
            }

            // Apply path prefix if specified
            $importedConfig = $this->applySourcePathPrefix($importedConfig, $importCfg->configDirectory());

            // Store for later merging
            $importedConfigs[] = $importedConfig;

            // Mark as processed
            $parsedImports[] = $importCfg->absolutePath;

            $this->logger?->debug('Successfully processed import', [
                'path' => $importCfg->path,
                'absolutePath' => $importCfg->absolutePath,
            ]);
        } catch (\Throwable $e) {
            $this->logger?->error('Failed to process import', [
                'path' => $importCfg->path,
                'absolutePath' => $importCfg->absolutePath,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            // Always end processing to maintain stack integrity
            $detector->endProcessing($importCfg->absolutePath);
        }
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
            $loader = $this->loaderFactory->createForFile(
                $this->dirs->withConfigPath($importConfig->absolutePath),
            );

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
     * Apply path prefix to the output path of all documents
     *
     * The pathPrefix specifies a subdirectory to prepend to all document outputPath values
     * in the imported configuration.
     *
     * Example:
     * - Document has outputPath: 'docs/api.md'
     * - Import has pathPrefix: 'api/v1'
     * - Resulting outputPath: 'api/v1/docs/api.md'
     *
     * @param array $config The imported configuration
     * @param string $pathPrefix The path prefix to apply
     * @return array The modified configuration with path prefix applied to document outputPaths
     */
    private function applyPathPrefix(array $config, string $pathPrefix): array
    {
        // Apply to document outputPath values
        if (isset($config['documents']) && \is_array($config['documents'])) {
            foreach ($config['documents'] as &$document) {
                if (isset($document['outputPath'])) {
                    $document['outputPath'] = $this->combinePaths($pathPrefix, $document['outputPath']);
                }
            }
        }

        return $config;
    }

    /**
     * Apply path prefix to relevant source paths
     */
    private function applySourcePathPrefix(array $config, string $pathPrefix): array
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
