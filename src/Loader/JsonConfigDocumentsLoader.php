<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Loader;

use Butschster\ContextGenerator\Document\DocumentRegistry;
use Butschster\ContextGenerator\DocumentsLoaderInterface;
use Butschster\ContextGenerator\FilesInterface;

final readonly class JsonConfigDocumentsLoader implements DocumentsLoaderInterface
{
    private JsonConfigParser $parser;

    /**
     * @param string $configPath Path to JSON configuration file (relative to root or absolute)
     */
    public function __construct(
        private FilesInterface $files,
        private string $configPath,
        string $rootPath,
    ) {
        $this->parser = new JsonConfigParser(
            rootPath: $rootPath,
        );
    }

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

        return $this->parser->parse($config);
    }

    public function isSupported(): bool
    {
        return \file_exists($this->configPath) && \pathinfo($this->configPath, PATHINFO_EXTENSION) === 'json';
    }
}
