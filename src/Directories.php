<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator;

use Butschster\ContextGenerator\Application\FSPath;
use Spiral\Core\Attribute\Singleton;

/**
 * This class manages application paths using FSPath for path manipulation.
 * It's immutable and provides methods to create new instances with modified paths.
 * It's a central component for path storage within the application.
 */
#[Singleton]
final readonly class Directories implements DirectoriesInterface
{
    private FSPath $rootPathObj;
    private FSPath $outputPathObj;
    private FSPath $configPathObj;
    private ?FSPath $envFilePathObj;

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

        // Initialize FSPath objects
        $this->rootPathObj = FSPath::create($rootPath);
        $this->outputPathObj = FSPath::create($outputPath);
        $this->configPathObj = FSPath::create($configPath);
        $this->envFilePathObj = $envFilePath !== null ? FSPath::create($envFilePath) : null;
    }

    /**
     * Get the root path of the project
     */
    public function getRootPath(): FSPath
    {
        return $this->rootPathObj;
    }

    /**
     * Get the output path where compiled documents will be saved
     */
    public function getOutputPath(): FSPath
    {
        return $this->outputPathObj;
    }

    /**
     * Get the path where configuration files are located
     */
    public function getConfigPath(): FSPath
    {
        return $this->configPathObj;
    }

    /**
     * Get the JSON schema path
     */
    public function getJsonSchemaPath(): string
    {
        return $this->jsonSchemaPath;
    }

    /**
     * Get the environment file path if set
     */
    public function getEnvFilePath(): ?FSPath
    {
        return $this->envFilePathObj;
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
     * Create a new instance with a different output path.
     *
     * @param string|null $outputPath The new output path or null to keep the current one
     * @return self A new instance with the updated output path
     */
    public function withOutputPath(?string $outputPath): self
    {
        if ($outputPath === null) {
            return $this;
        }

        return new self(
            rootPath: $this->rootPath,
            outputPath: $outputPath,
            configPath: $this->configPath,
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

        $envFilePath = $this->rootPathObj->join($envFileName)->toString();

        return new self(
            rootPath: $this->rootPath,
            outputPath: $this->outputPath,
            configPath: $this->configPath,
            jsonSchemaPath: $this->jsonSchemaPath,
            envFilePath: $envFilePath,
        );
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
        $configPathObj = FSPath::create($configPath);

        if ($configPathObj->isRelative()) {
            $configPath = $this->rootPathObj->join($configPath)->toString();
        }

        // If config path is absolute, use its directory as root
        $configDir = \rtrim(\is_dir($configPath) ? $configPath : \dirname($configPath), '/');

        if (\is_dir($configDir)) {
            return $this->withRootPath($configDir)->withConfigPath($configPath);
        }

        return $this;
    }
}
