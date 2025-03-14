<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Loader;

use Butschster\ContextGenerator\DocumentsLoaderInterface;
use Butschster\ContextGenerator\Loader\ConfigRegistry\DocumentRegistry;

final readonly class ConfigDocumentsLoader implements DocumentsLoaderInterface
{
    /**
     * @param string $configPath Path to configuration file (relative to root or absolute)
     */
    public function __construct(
        private string $configPath,
    ) {}

    public function isSupported(): bool
    {
        return \is_file($this->configPath) && \pathinfo($this->configPath, PATHINFO_EXTENSION) === 'php';
    }

    public function load(): DocumentRegistry
    {
        $registry = require $this->configPath;

        if (!$registry instanceof DocumentRegistry) {
            throw new \RuntimeException(
                'Configuration file must return a DocumentRegistry instance',
            );
        }

        return $registry;
    }
}
