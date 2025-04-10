<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Loader;

use Butschster\ContextGenerator\Application\AppScope;
use Butschster\ContextGenerator\Config\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\Config\Registry\ConfigRegistry;
use Spiral\Core\Attribute\Scope;

/**
 * Interface for configuration loaders
 */
#[Scope(name: AppScope::Mcp)]
interface ConfigLoaderInterface
{
    /**
     * Load configuration and return a config registry
     *
     * @throws ConfigLoaderException If loading fails
     */
    public function load(): ConfigRegistry;

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
