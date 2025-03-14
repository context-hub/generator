<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Loader;

use Butschster\ContextGenerator\DocumentsLoaderInterface;
use Butschster\ContextGenerator\FilesInterface;
use Butschster\ContextGenerator\Loader\ConfigRegistry\DocumentRegistry;
use Butschster\ContextGenerator\Loader\ConfigRegistry\Parser\ConfigParserInterface;

final readonly class JsonConfigDocumentsLoader implements DocumentsLoaderInterface
{
    /**
     * @param string $configPath Path to JSON configuration file (relative to root or absolute)
     */
    public function __construct(
        private FilesInterface $files,
        private ConfigParserInterface $parser,
        private string $configPath,
        string $rootPath,
    ) {}

    public function load(): DocumentRegistry
    {
        $configFile = $this->configPath;

        $jsonContent = $this->files->read($configFile);

        if ($jsonContent === false) {
            throw new \InvalidArgumentException(
                \sprintf('Unable to read configuration file: %s', $configFile),
            );
        }

        try {
            $config = \json_decode($jsonContent, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException(
                \sprintf('Invalid JSON configuration file: %s', $configFile),
                previous: $e,
            );
        }

        $configRegistry = $this->parser->parse($config);

        // Get the DocumentRegistry from the ConfigRegistry
        if (!$configRegistry->has('documents')) {
            throw new \RuntimeException('No documents found in configuration');
        }

        return $configRegistry->get('documents', DocumentRegistry::class);
    }

    public function isSupported(): bool
    {
        return \file_exists($this->configPath) && \pathinfo($this->configPath, PATHINFO_EXTENSION) === 'json';
    }
}
