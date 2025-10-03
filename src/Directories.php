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
    private FSPath $binaryPathObj;
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
        public ?string $binaryPath = null,
    ) {
        // Ensure paths are not empty
        if ($rootPath === '') {
            throw new \InvalidArgumentException(message: 'Root path cannot be empty');
        }
        if ($outputPath === '') {
            throw new \InvalidArgumentException(message: 'Output path cannot be empty');
        }
        if ($configPath === '') {
            throw new \InvalidArgumentException(message: 'Config path cannot be empty');
        }
        if ($jsonSchemaPath === '') {
            throw new \InvalidArgumentException(message: 'JSON schema path cannot be empty');
        }

        // Initialize FSPath objects
        $this->binaryPathObj = FSPath::create(path: $this->binaryPath ?? $this->resolveBinaryPath());
        $this->rootPathObj = FSPath::create(path: $rootPath);
        $this->outputPathObj = FSPath::create(path: $outputPath);
        $this->configPathObj = FSPath::create(path: $configPath);
        $this->envFilePathObj = $envFilePath !== null ? FSPath::create(path: $envFilePath) : null;
    }

    public function getBinaryPath(): FSPath
    {
        return $this->binaryPathObj;
    }

    public function getRootPath(): FSPath
    {
        return $this->rootPathObj;
    }

    public function getOutputPath(): FSPath
    {
        return $this->outputPathObj;
    }

    public function getConfigPath(): FSPath
    {
        return $this->configPathObj;
    }

    public function getJsonSchemaPath(): string
    {
        return $this->jsonSchemaPath;
    }

    public function getEnvFilePath(): ?FSPath
    {
        return $this->envFilePathObj;
    }

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
            binaryPath: $this->binaryPath,
        );
    }

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
            binaryPath: $this->binaryPath,
        );
    }

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
            binaryPath: $this->binaryPath,
        );
    }

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
            binaryPath: $this->binaryPath,
        );
    }

    public function determineRootPath(?string $configPath = null, ?string $inlineConfig = null): self
    {
        if ($configPath === null || $inlineConfig !== null) {
            return $this;
        }

        // If relative, resolve against the original root path
        $configPathObj = FSPath::create(path: $configPath);

        if ($configPathObj->isRelative()) {
            $configPath = $this->rootPathObj->join($configPath)->toString();
        }

        // If config path is absolute, use its directory as root
        $configDir = \rtrim(string: \is_dir(filename: $configPath) ? $configPath : \dirname(path: $configPath), characters: '/');

        if (\is_dir(filename: $configDir)) {
            return $this->withRootPath(rootPath: $configDir)->withConfigPath(configPath: $configPath);
        }

        return $this;
    }

    private function resolveBinaryPath(): string
    {
        // Primary: Use argv[0] (most reliable for CLI)
        if (isset($_SERVER['argv'][0])) {
            return $_SERVER['argv'][0];
        }

        // Fallback 1: PHP_SELF
        if (isset($_SERVER['PHP_SELF'])) {
            return $_SERVER['PHP_SELF'];
        }

        // Fallback 2: SCRIPT_NAME
        if (isset($_SERVER['SCRIPT_NAME'])) {
            return $_SERVER['SCRIPT_NAME'];
        }

        throw new \RuntimeException(message: 'Unable to determine binary path');
    }
}
