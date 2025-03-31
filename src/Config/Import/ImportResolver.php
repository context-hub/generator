<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import;

use Butschster\ContextGenerator\Config\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\Config\Import\PathPrefixer\DocumentOutputPathPrefixer;
use Butschster\ContextGenerator\Config\Import\PathPrefixer\SourcePathPrefixer;
use Butschster\ContextGenerator\Config\Loader\ConfigLoaderFactoryInterface;
use Butschster\ContextGenerator\Directories;
use Psr\Log\LoggerInterface;
use Spiral\Files\FilesInterface;

/**
 * Resolves import directives in configuration files
 */
final readonly class ImportResolver
{
    private WildcardPathFinder $pathFinder;
    private DocumentOutputPathPrefixer $documentPrefixer;
    private SourcePathPrefixer $sourcePrefixer;

    public function __construct(
        private Directories $dirs,
        private FilesInterface $files,
        private ConfigLoaderFactoryInterface $loaderFactory,
        private ?LoggerInterface $logger = null,
    ) {
        $this->pathFinder = new WildcardPathFinder($files, $logger);
        $this->documentPrefixer = new DocumentOutputPathPrefixer();
        $this->sourcePrefixer = new SourcePathPrefixer();
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
        CircularImportDetectorInterface $detector,
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
                $importedConfig = $this->documentPrefixer->applyPrefix($importedConfig, $importCfg->pathPrefix);
            }

            // Apply source path prefix
            $importedConfig = $this->sourcePrefixer->applyPrefix($importedConfig, $importCfg->configDirectory());

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
