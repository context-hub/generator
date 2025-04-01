<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import;

use Butschster\ContextGenerator\Config\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\Config\Import\PathPrefixer\DocumentOutputPathPrefixer;
use Butschster\ContextGenerator\Config\Import\PathPrefixer\SourcePathPrefixer;
use Butschster\ContextGenerator\Config\Import\Source\Exception\ImportSourceException;
use Butschster\ContextGenerator\Config\Import\Source\Registry\ImportSourceRegistry;
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
        FilesInterface $files,
        private ImportSourceRegistry $sourceRegistry,
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

            // Handle wildcard paths (only for local sources)
            if ($importCfg->type === ImportConfig::TYPE_LOCAL && $importCfg->hasWildcard) {
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
                    'type' => $importCfg->type,
                ]);
                continue;
            }

            // Process a single import
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
                type: ImportConfig::TYPE_LOCAL, // Wildcard imports are always local
                pathPrefix: $importCfg->pathPrefix,
                hasWildcard: false,
                docs: $importCfg->docs,
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
        // For external sources, use path as the identifier for circular detection
        $importId = $importCfg->absolutePath;

        // Check for circular imports
        $detector->beginProcessing($importId);

        try {
            // Find an appropriate import source
            $importSource = $this->sourceRegistry->findForConfig($importCfg);

            if (!$importSource) {
                throw new ImportSourceException(
                    \sprintf('No import source found for type "%s" and path "%s"', $importCfg->type, $importCfg->path),
                );
            }

            // Load the configuration using the appropriate source
            $importedConfig = $importSource->load($importCfg);

            // For external sources, we need to resolve the base path differently
            $importBasePath = match ($importCfg->type) {
                ImportConfig::TYPE_LOCAL => \dirname($importCfg->absolutePath),
                ImportConfig::TYPE_GITHUB => '', // GitHub paths are relative to repo root
                ImportConfig::TYPE_COMPOSER => \dirname($importCfg->path),
                ImportConfig::TYPE_URL => '', // URL paths are absolute
                default => $basePath,
            };

            // Recursively process nested imports
            $importedConfig = $this->resolveImports(
                $importedConfig,
                $importBasePath,
                $parsedImports,
                $detector,
            );

            // Apply path prefix for output document paths if specified
            if ($importCfg->pathPrefix !== null) {
                $importedConfig = $this->documentPrefixer->applyPrefix($importedConfig, $importCfg->pathPrefix);
            }

            // Apply source path prefix for local imports
            if ($importCfg->type === ImportConfig::TYPE_LOCAL) {
                $importedConfig = $this->sourcePrefixer->applyPrefix($importedConfig, $importCfg->configDirectory());
            }

            // Store for later merging
            $importedConfigs[] = $importedConfig;

            // Mark as processed
            $parsedImports[] = $importId;

            $this->logger?->debug('Successfully processed import', [
                'path' => $importCfg->path,
                'type' => $importCfg->type,
            ]);
        } catch (\Throwable $e) {
            $this->logger?->error('Failed to process import', [
                'path' => $importCfg->path,
                'type' => $importCfg->type,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            // Always end processing to maintain stack integrity
            $detector->endProcessing($importId);
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
