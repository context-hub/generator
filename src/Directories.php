<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator;

/**
 * This class is immutable and provides methods to create new instances with modified paths.
 * It's a central component for path resolution and management within the application.
 */
final readonly class Directories
{
    /**
     * Create a new Directories instance with the specified paths.
     *
     * @param string $rootPath The root path of the project, used as base for resolving relative paths
     * @param string $outputPath The path where compiled documents will be saved
     * @param string $configPath The path where configuration files are located
     * @param string $jsonSchemaPath The path to the JSON schema file
     * @param string|null $envFilePath Optional path to an environment file
     */
    public function __construct(
        public string $rootPath,
        public string $outputPath,
        public string $configPath,
        public string $jsonSchemaPath,
        public ?string $envFilePath = null,
    ) {
        // Ensure paths are not empty
        if ($rootPath === '') {
            throw new \InvalidArgumentException('Root path cannot be empty');
        }
        if ($outputPath === '') {
            throw new \InvalidArgumentException('Output path cannot be empty');
        }
        if ($configPath === '') {
            throw new \InvalidArgumentException('Config path cannot be empty');
        }
        if ($jsonSchemaPath === '') {
            throw new \InvalidArgumentException('JSON schema path cannot be empty');
        }
    }

    /**
     * Create a new instance with a different root path.
     *
     * @param string|null $rootPath The new root path or null to keep the current one
     * @return self A new instance with the updated root path
     */
    public function withRootPath(?string $rootPath): self
    {
        if ($rootPath === null) {
            return $this;
        }

        return new self(
            rootPath: $rootPath,
            outputPath: $this->outputPath,
            configPath: $this->configPath,
            jsonSchemaPath: $this->jsonSchemaPath,
            envFilePath: $this->envFilePath,
        );
    }

    /**
     * Create a new instance with a different config path.
     *
     * @param string|null $configPath The new config path or null to keep the current one
     * @return self A new instance with the updated config path
     */
    public function withConfigPath(?string $configPath): self
    {
        if ($configPath === null) {
            return $this;
        }

        return new self(
            rootPath: $this->rootPath,
            outputPath: $this->outputPath,
            configPath: $configPath,
            jsonSchemaPath: $this->jsonSchemaPath,
            envFilePath: $this->envFilePath,
        );
    }

    /**
     * Create a new instance with an environment file path derived from the given filename.
     *
     * @param string|null $envFileName The environment filename or null to keep the current one
     * @return self A new instance with the updated environment file path
     */
    public function withEnvFile(?string $envFileName): self
    {
        if ($envFileName === null) {
            return $this;
        }

        return new self(
            rootPath: $this->rootPath,
            outputPath: $this->outputPath,
            configPath: $this->configPath,
            jsonSchemaPath: $this->jsonSchemaPath,
            envFilePath: $this->combinePaths($this->rootPath, $envFileName),
        );
    }

    /**
     * Get the absolute path for a file relative to the root path.
     *
     * @param string $filename The relative file path
     * @return string The absolute file path
     */
    public function getFilePath(string $filename): string
    {
        return $this->combinePaths($this->rootPath, $filename);
    }

    /**
     * Get the absolute path for a file relative to the config path.
     *
     * @param string $filename The relative file path
     * @return string The absolute file path
     */
    public function getConfigPath(string $filename): string
    {
        return $this->combinePaths($this->configPath, $filename);
    }

    /**
     * Determine if a path is absolute.
     *
     * @param string $path The path to check
     * @return bool True if the path is absolute, false otherwise
     */
    public function isAbsolutePath(string $path): bool
    {
        return \str_starts_with($path, '/');
    }

    /**
     * Resolve a path relative to another path.
     *
     * @param string $basePath The base path
     * @param string $path The path to resolve
     * @return string The resolved path
     */
    public function resolvePath(string $basePath, string $path): string
    {
        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return $this->combinePaths($basePath, $path);
    }

    /**
     * Combine two paths, ensuring they are properly joined with a single slash.
     *
     * @param string $basePath The base path
     * @param string $path The path to append
     * @return string The combined path
     */
    public function combinePaths(string $basePath, string $path): string
    {
        return \rtrim($basePath, '/') . '/' . \ltrim($path, '/');
    }

    /**
     * Determine the effective root path based on config file path.
     *
     * This method is used to adjust the root path when a specific configuration
     * file is provided, making relative paths within that configuration work correctly.
     *
     * @param string|null $configPath The configuration file path or null
     * @param string|null $inlineConfig Inline configuration content or null
     * @return self A new instance with adjusted paths based on the config location
     */
    public function determineRootPath(?string $configPath = null, ?string $inlineConfig = null): self
    {
        if ($configPath === null || $inlineConfig !== null) {
            return $this;
        }

        // If relative, resolve against the original root path
        if (!$this->isAbsolutePath($configPath)) {
            $configPath = $this->combinePaths($this->rootPath, $configPath);
        }

        // If config path is absolute, use its directory as root
        $configDir = \rtrim(\is_dir($configPath) ? $configPath : \dirname($configPath), '/');

        if (\is_dir($configDir)) {
            return $this->withRootPath($configDir)->withConfigPath($configPath);
        }

        return $this;
    }
}
