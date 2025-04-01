<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Loader;

/**
 * Interface for factories that create config loaders
 */
interface ConfigLoaderFactoryInterface
{
    /**
     * Create a loader for a specific config file
     */
    public function create(string $configPath): ConfigLoaderInterface;

    /**
     * Create a loader for a specific file path
     */
    public function createForFile(string $configPath): ConfigLoaderInterface;

    /**
     * Create a loader for an inline JSON configuration string
     *
     * @param string $jsonConfig The JSON configuration string
     */
    public function createFromString(string $jsonConfig): ConfigLoaderInterface;
}
