<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator;

final readonly class Directories
{
    public function __construct(
        public string $rootPath,
        public string $outputPath,
        public string $configPath,
        public string $jsonSchemaPath,
        public ?string $envFilePath = null,
    ) {}

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
            envFilePath: \sprintf('%s/%s', $this->rootPath, \ltrim($envFileName, '/')),
        );
    }

    public function getFilePath(string $filename): string
    {
        $filename = \ltrim($filename, '/');

        return \sprintf('%s/%s', $this->rootPath, $filename);
    }

    /**
     * Determine the effective root path based on config file path
     */
    public function determineRootPath(?string $configPath = null, ?string $inlineConfig = null): self
    {
        if ($configPath === null || $inlineConfig !== null) {
            return $this;
        }

        // If config path is absolute, use its directory as root
        if (\str_starts_with($configPath, '/')) {
            $configDir = \rtrim(\is_dir($configPath) ? $configPath : \dirname($configPath));
        } else {
            // If relative, resolve against the original root path
            $fullConfigPath = \rtrim($this->rootPath, '/') . '/' . $configPath;

            $configDir = \rtrim(\is_dir($fullConfigPath) ? $fullConfigPath : \dirname($fullConfigPath));
        }

        if (\file_exists($configDir) && \is_dir($configDir)) {
            return $this->withRootPath($configDir)->withConfigPath($configPath);
        }

        return $this;
    }
}
