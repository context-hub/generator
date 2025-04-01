<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import\Source;

use Butschster\ContextGenerator\Config\Import\ImportConfig;
use Butschster\ContextGenerator\Config\Reader\JsonReader;
use Butschster\ContextGenerator\Config\Reader\YamlReader;
use Butschster\ContextGenerator\Source\Composer\Client\ComposerClientInterface;
use Psr\Log\LoggerInterface;
use Spiral\Files\FilesInterface;

/**
 * Import source for Composer package configurations
 */
final class ComposerImportSource extends AbstractImportSource
{
    public function __construct(
        private readonly FilesInterface $files,
        private readonly ComposerClientInterface $composerClient,
        private readonly JsonReader $jsonReader,
        private readonly YamlReader $yamlReader,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($logger);
    }

    public function getName(): string
    {
        return 'composer';
    }

    public function supports(ImportConfig $config): bool
    {
        return ($config->type ?? 'local') === 'composer';
    }

    public function load(ImportConfig $config): array
    {
        if (!$this->supports($config)) {
            throw Exception\ImportSourceException::sourceNotSupported(
                $config->path,
                $config->type ?? 'unknown',
            );
        }

        try {
            $this->logger->debug('Loading Composer import', [
                'path' => $config->path,
            ]);

            // Determine the project root directory
            $projectRootDir = $this->getProjectRootDir();

            // Load composer.json to find the vendor directory
            $composerData = $this->composerClient->loadComposerData($projectRootDir);
            $vendorDir = $this->composerClient->getVendorDir($composerData, $projectRootDir);

            // Resolve the full path to the imported file
            $importPath = $this->resolveImportPath($config->path, $projectRootDir, $vendorDir);

            if (!$this->files->exists($importPath)) {
                throw Exception\ImportSourceException::fileNotFound($importPath);
            }

            // Get the appropriate reader for the file
            $reader = $this->getReaderForFile($importPath);
            if (!$reader) {
                throw new Exception\ImportSourceException(
                    \sprintf('Unsupported file format for import: %s', $importPath),
                );
            }

            // Read the configuration
            $importedConfig = $this->readConfig($importPath, $reader);

            // Process selective imports if specified
            return $this->processSelectiveImports($importedConfig, $config);
        } catch (\Throwable $e) {
            $this->logger->error('Composer import failed', [
                'path' => $config->path,
                'error' => $e->getMessage(),
            ]);

            throw Exception\ImportSourceException::composerPackageNotFound($config->path);
        }
    }

    /**
     * Find the project root directory (where composer.json is located)
     */
    private function getProjectRootDir(): string
    {
        // Start from current directory and go up until composer.json is found
        $dir = \getcwd();

        while ($dir !== '/') {
            if ($this->files->exists($dir . '/composer.json')) {
                return $dir;
            }

            $parentDir = \dirname($dir);
            if ($parentDir === $dir) {
                break; // Prevent infinite loop at filesystem root
            }

            $dir = $parentDir;
        }

        // Default to current directory if composer.json not found
        return \getcwd();
    }

    /**
     * Resolve the full path to the imported file
     */
    private function resolveImportPath(string $path, string $projectRootDir, string $vendorDir): string
    {
        // If the path already starts with vendor directory
        if (str_starts_with($path, $vendorDir)) {
            return \rtrim($projectRootDir, '/') . '/' . $path;
        }

        // If the path points to a file in a package (e.g., vendor/package/file.yaml)
        if (str_starts_with($path, 'vendor/')) {
            return \rtrim($projectRootDir, '/') . '/' . $path;
        }

        // If the path is a package path without vendor prefix
        return \rtrim($projectRootDir, '/') . '/' . $vendorDir . '/' . $path;
    }

    /**
     * Get an appropriate reader for the given file
     */
    private function getReaderForFile(string $path): ?object
    {
        $extension = \pathinfo($path, PATHINFO_EXTENSION);

        return match ($extension) {
            'json' => $this->jsonReader,
            'yaml', 'yml' => $this->yamlReader,
            default => null,
        };
    }
}
