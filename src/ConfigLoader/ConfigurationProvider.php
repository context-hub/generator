<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\ConfigLoader;

use Butschster\ContextGenerator\ConfigLoader\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\FilesInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for providing configuration loaders based on different sources
 */
final readonly class ConfigurationProvider
{
    public function __construct(
        private ConfigLoaderFactory $loaderFactory,
        private FilesInterface $files,
        private string $rootPath,
        private ?LoggerInterface $logger = null,
        private array $parserPlugins = [],
    ) {}

    /**
     * Get a config loader from an inline JSON string
     */
    public function fromString(string $jsonConfig): ConfigLoaderInterface
    {
        $this->logger?->info('Using inline JSON configuration');

        return $this->loaderFactory->createFromString(
            jsonConfig: $jsonConfig,
            parserPlugins: $this->parserPlugins,
        );
    }

    /**
     * Get a config loader for a specific path (file or directory)
     */
    public function fromPath(string $configPath): ConfigLoaderInterface
    {
        $resolvedPath = $this->resolvePath($configPath);

        if (\is_dir($resolvedPath)) {
            $this->logger?->info('Looking for configuration files in directory', [
                'directory' => $resolvedPath,
            ]);

            return $this->loaderFactory->create(
                rootPath: $resolvedPath,
                parserPlugins: $this->parserPlugins,
            );
        }
        $this->logger?->info('Loading configuration from specific file', [
            'file' => $resolvedPath,
        ]);

        return $this->loaderFactory->createForFile(
            filePath: $resolvedPath,
            parserPlugins: $this->parserPlugins,
        );

    }

    /**
     * Get a config loader using the default paths
     */
    public function fromDefaultLocation(): ConfigLoaderInterface
    {
        $this->logger?->info('Loading configuration from default location', [
            'rootPath' => $this->rootPath,
        ]);

        return $this->loaderFactory->create(
            rootPath: $this->rootPath,
            parserPlugins: $this->parserPlugins,
        );
    }

    /**
     * Resolve a path (absolute or relative to root path)
     */
    private function resolvePath(string $path): string
    {
        // If it's an absolute path, use it directly
        if ($this->isAbsolutePath($path)) {
            $resolvedPath = $path;
        } else {
            // Otherwise, resolve it relative to the root path
            $resolvedPath = \rtrim($this->rootPath, '/') . '/' . $path;
        }

        // Check if the path exists
        if (!$this->files->exists($resolvedPath)) {
            throw new ConfigLoaderException(\sprintf('Path not found: %s', $resolvedPath));
        }

        return $resolvedPath;
    }

    /**
     * Check if a path is absolute
     */
    private function isAbsolutePath(string $path): bool
    {
        return \str_starts_with($path, '/');
    }
}
