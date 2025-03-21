<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\ConfigLoader;

use Butschster\ContextGenerator\ConfigLoader\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\ConfigLoader\Registry\DocumentRegistry;

/**
 * Interface for configuration loaders
 */
interface ConfigLoaderInterface
{
    /**
     * Load configuration and return a document registry
     *
     * @return DocumentRegistry The loaded document registry
     * @throws ConfigLoaderException If loading fails
     */
    public function load(): DocumentRegistry;

    /**
     * Check if this loader can load configuration
     *
     * @return bool True if the loader can load configuration
     */
    public function isSupported(): bool;
}
