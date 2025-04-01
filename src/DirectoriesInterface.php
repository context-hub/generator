<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator;

/**
 * Interface for application directory and path management.
 * 
 * Provides methods to access and manipulate application paths.
 */
interface DirectoriesInterface
{
    /**
     * Get the root path of the project
     */
    public function getRootPath(): string;
    
    /**
     * Get the output path where compiled documents will be saved
     */
    public function getOutputPath(): string;
    
    /**
     * Get the path where configuration files are located
     */
    public function getConfigPath(): string;
    
    /**
     * Get the JSON schema path
     */
    public function getJsonSchemaPath(): string;
    
    /**
     * Get the environment file path if set
     */
    public function getEnvFilePath(): ?string;
    
    /**
     * Create a new instance with a different root path.
     */
    public function withRootPath(?string $rootPath): self;
    
    /**
     * Create a new instance with a different config path.
     */
    public function withConfigPath(?string $configPath): self;
    
    /**
     * Create a new instance with an environment file path.
     */
    public function withEnvFile(?string $envFileName): self;
    
    /**
     * Get the absolute path for a file relative to the root path.
     */
    public function getFilePath(string $filename): string;
    
    /**
     * Get the absolute path for a file relative to the config path.
     */
    public function getConfigFilePath(string $filename): string;
    
    /**
     * Determine the effective root path based on config file path.
     */
    public function determineRootPath(?string $configPath = null, ?string $inlineConfig = null): self;
    
    /**
     * Resolve a path relative to another path.
     */
    public function resolvePath(string $basePath, string $path): string;
    
    /**
     * Combine two paths, ensuring they are properly joined.
     */
    public function combinePaths(string $basePath, string $path): string;
    
    /**
     * Determine if a path is absolute.
     */
    public function isAbsolutePath(string $path): bool;
}
