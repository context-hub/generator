<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator;

interface DocumentsLoaderInterface
{
    /**
     * Load documents into a registry
     */
    public function load(): DocumentRegistry;

    /**
     * Check if the loader is supported
     */
    public function isSupported(): bool;
}
