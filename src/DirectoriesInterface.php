<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator;

use Butschster\ContextGenerator\Application\FSPath;

/**
 * Interface for application directory and path management.
 *
 * Provides access to essential application paths as FSPath objects.
 */
interface DirectoriesInterface
{
    /**
     * Get the root path of the project
     */
    public function getRootPath(): FSPath;

    /**
     * Get the output path where compiled documents will be saved
     */
    public function getOutputPath(): FSPath;

    /**
     * Get the path where configuration files are located
     */
    public function getConfigPath(): FSPath;

    /**
     * Get the JSON schema path
     */
    public function getJsonSchemaPath(): string;

    /**
     * Get the environment file path if set
     */
    public function getEnvFilePath(): ?FSPath;

    /**
     * Create a new instance with a different root path.
     */
    public function withRootPath(?string $rootPath): self;

    /**
     * Create a new instance with a different config path.
     */
    public function withConfigPath(?string $configPath): self;

    /**
     * Create a new instance with a different output path.
     */
    public function withOutputPath(?string $outputPath): self;

    /**
     * Create a new instance with an environment file path.
     */
    public function withEnvFile(?string $envFileName): self;

    /**
     * Determine the effective root path based on config file path.
     *
     * This method is used to adjust the root path when a specific configuration
     * file is provided, making relative paths within that configuration work correctly.
     */
    public function determineRootPath(?string $configPath = null, ?string $inlineConfig = null): self;
}
