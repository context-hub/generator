<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import;

use Butschster\ContextGenerator\Config\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\Config\Import\PathPrefixer\DocumentOutputPathPrefixer;
use Butschster\ContextGenerator\Config\Import\PathPrefixer\SourcePathPrefixer;
use Butschster\ContextGenerator\Config\Import\Source\Config\SourceConfigInterface;
use Butschster\ContextGenerator\Config\Import\Source\Config\SourceConfigFactory;
use Butschster\ContextGenerator\Config\Import\Source\Exception\ImportSourceException;
use Butschster\ContextGenerator\Config\Import\Source\ImportSourceProvider;
use Butschster\ContextGenerator\Config\Import\Source\Local\LocalSourceConfig;
use Butschster\ContextGenerator\Config\Import\Source\Url\UrlSourceConfig;
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
    private SourceConfigFactory $sourceConfigFactory;

    public function __construct(
        private Directories $dirs,
        FilesInterface $files,
        private ImportSourceProvider $sourceProvider,
        private ?LoggerInterface $logger = null,
    ) {
        $this->pathFinder = new WildcardPathFinder($files, $logger);
        $this->documentPrefixer = new DocumentOutputPathPrefixer();
        $this->sourcePrefixer = new SourcePathPrefixer();
        $this->sourceConfigFactory = new SourceConfigFactory();
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
            // Create source configuration from the raw import config
            $sourceConfig = $this->sourceConfigFactory->createFromArray($importConfig, $basePath);

            // Handle wildcard paths (only for local sources)
            if ($sourceConfig instanceof LocalSourceConfig && $sourceConfig->hasWildcard()) {
                $this->processWildcardImport(
                    $sourceConfig,
                    $basePath,
                    $parsedImports,
                    $detector,
                    $importedConfigs,
                );
                continue;
            }

            // For local imports, check if already processed
            if ($sourceConfig instanceof LocalSourceConfig) {
                $absolutePath = $sourceConfig->getAbsolutePath();
                if (\in_array($absolutePath, $parsedImports, true)) {
                    $this->logger?->debug('Skipping already processed import', [
                        'path' => $sourceConfig->getPath(),
                        'type' => $sourceConfig->getType(),
                    ]);
                    continue;
                }
            }

            // Process a single import
            $this->processSingleImport(
                $sourceConfig,
                $basePath,
                $parsedImports,
                $detector,
                $importedConfigs,
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
        LocalSourceConfig $sourceConfig,
        string $basePath,
        array &$parsedImports,
        CircularImportDetectorInterface $detector,
        array &$importedConfigs,
    ): void {
        // Find all files that match the pattern
        $matchingPaths = $this->pathFinder->findMatchingPaths($sourceConfig->getPath(), $basePath);

        if (empty($matchingPaths)) {
            $this->logger?->warning('No files match the wildcard pattern', [
                'pattern' => $sourceConfig->getPath(),
                'basePath' => $basePath,
            ]);
            return;
        }

        $this->logger?->debug('Found files matching wildcard pattern', [
            'pattern' => $sourceConfig->getPath(),
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

            // Create a local source config for this match
            $localConfig = new LocalSourceConfig(
                path: \ltrim(\str_replace($this->dirs->rootPath, '', $matchingPath), '/'),
                absolutePath: $matchingPath,
                hasWildcard: false,
                pathPrefix: $sourceConfig->getPathPrefix(),
                selectiveDocuments: $sourceConfig->getSelectiveDocuments(),
            );

            // Process it using the standard import logic
            $this->processSingleImport(
                $localConfig,
                \dirname($matchingPath), // Base path is the directory of the matched file
                $parsedImports,
                $detector,
                $importedConfigs,
            );
        }
    }

    /**
     * Process a single non-wildcard import
     */
    private function processSingleImport(
        SourceConfigInterface $sourceConfig,
        string $basePath,
        array &$parsedImports,
        CircularImportDetectorInterface $detector,
        array &$importedConfigs,
    ): void {
        // For circular dependency detection
        $importId = match (true) {
            $sourceConfig instanceof LocalSourceConfig => $sourceConfig->getAbsolutePath(),
            $sourceConfig instanceof UrlSourceConfig => $sourceConfig->getPath(),
        };

        // Check for circular imports
        $detector->beginProcessing($importId);

        try {
            // Find an appropriate import source using the provider
            $importSource = $this->sourceProvider->findSourceForConfig($sourceConfig);

            if (!$importSource) {
                throw new ImportSourceException(
                    \sprintf(
                        'No import source found for type "%s" and path "%s"',
                        $sourceConfig->getType(),
                        $sourceConfig->getPath(),
                    ),
                );
            }

            // Load the configuration using the appropriate source
            $importedConfig = $importSource->load($sourceConfig);

            // For external sources, we need to resolve the base path differently
            $importBasePath = match ($sourceConfig->getType()) {
                'local' => $sourceConfig->getConfigDirectory(),
                'url' => '', // URL paths are absolute
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

            // Apply source path prefix for local imports
            if ($sourceConfig instanceof LocalSourceConfig) {
                if ($sourceConfig->getPathPrefix() !== null) {
                    $importedConfig = $this->documentPrefixer->applyPrefix(
                        $importedConfig,
                        $sourceConfig->getPathPrefix(),
                    );
                }

                $importedConfig = $this->sourcePrefixer->applyPrefix(
                    $importedConfig,
                    $sourceConfig->getConfigDirectory(),
                );

                // Mark as processed for local sources
                $parsedImports[] = $sourceConfig->getAbsolutePath();
            }

            // Store for later merging
            $importedConfigs[] = $importedConfig;

            $this->logger?->debug('Successfully processed import', [
                'type' => $sourceConfig->getType(),
            ]);
        } catch (\Throwable $e) {
            $this->logger?->error('Failed to process import', [
                'type' => $sourceConfig->getType(),
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
