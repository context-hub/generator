<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Loader;

use Butschster\ContextGenerator\Config\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\Config\Registry\RegistryInterface;

/**
 * Interface for configuration loaders
 */
interface ConfigLoaderInterface
{
    /**
     * Load configuration and return a document registry
     *
     * @throws ConfigLoaderException If loading fails
     */
    public function load(): RegistryInterface;

    /**
     * Load raw configuration from the first supported loader
     */
    public function loadRawConfig(): array;

    /**
     * Check if this loader can load configuration
     *
     * @return bool True if the loader can load configuration
     */
    public function isSupported(): bool;
}
