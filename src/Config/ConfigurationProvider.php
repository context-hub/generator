<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\Config\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\Config\Loader\ConfigLoaderFactoryInterface;
use Butschster\ContextGenerator\Config\Loader\ConfigLoaderInterface;
use Butschster\ContextGenerator\DirectoriesInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for providing configuration loaders based on different sources
 */
final readonly class ConfigurationProvider
{
    public function __construct(
        private ConfigLoaderFactoryInterface $loaderFactory,
        private DirectoriesInterface $dirs,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Get a config loader from an inline JSON string
     */
    public function fromString(string $jsonConfig): ConfigLoaderInterface
    {
        $this->logger?->info('Using inline JSON configuration');

        return $this->loaderFactory->createFromString(
            jsonConfig: $jsonConfig,
        );
    }

    /**
     * Get a config loader for a specific path (file or directory)
     */
    public function fromPath(string $configPath): ConfigLoaderInterface
    {
        $resolvedPath = $this->resolvePath($configPath);

        if ($resolvedPath->isDir()) {
            $this->logger?->info('Looking for configuration files in directory', [
                'directory' => $resolvedPath,
            ]);

            return $this->loaderFactory->create((string) $resolvedPath);
        }
        $this->logger?->info('Loading configuration from specific file', [
            'file' => $resolvedPath,
        ]);

        return $this->loaderFactory->createForFile((string) $resolvedPath);
    }

    /**
     * Get a config loader using the default paths
     */
    public function fromDefaultLocation(): ConfigLoaderInterface
    {
        $this->logger?->info('Loading configuration from default location', [
            'rootPath' => (string) $this->dirs->getConfigPath(),
        ]);

        return $this->loaderFactory->create((string) $this->dirs->getConfigPath());
    }

    /**
     * Resolve a path (absolute or relative to root path)
     */
    private function resolvePath(string $path): FSPath
    {
        $pathObj = FSPath::create($path);

        // If it's an absolute path, use it directly
        if ($pathObj->isAbsolute()) {
            $resolvedPath = $pathObj;
        } else {
            // Otherwise, resolve it relative to the root path
            $resolvedPath = $this->dirs->getRootPath()->join($path);
        }

        // Check if the path exists
        if (!$resolvedPath->exists()) {
            throw new ConfigLoaderException(\sprintf('Path not found: %s', $resolvedPath));
        }

        return $resolvedPath;
    }
}
