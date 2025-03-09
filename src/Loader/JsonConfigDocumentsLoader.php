<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Loader;

use Butschster\ContextGenerator\Document;
use Butschster\ContextGenerator\DocumentRegistry;
use Butschster\ContextGenerator\DocumentsLoaderInterface;
use Butschster\ContextGenerator\FilesInterface;
use Butschster\ContextGenerator\Source\FileSource;
use Butschster\ContextGenerator\Source\PhpClassSource;
use Butschster\ContextGenerator\Source\TextSource;
use Butschster\ContextGenerator\Source\UrlSource;

final readonly class JsonConfigDocumentsLoader implements DocumentsLoaderInterface
{
    /**
     * @param string $configPath Path to JSON configuration file (relative to root or absolute)
     */
    public function __construct(
        private FilesInterface $files,
        private string $configPath,
        private string $rootPath,
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

        return $this->createDocumentRegistry($config);
    }

    /**
     * Create a DocumentRegistry from the decoded JSON configuration.
     */
    private function createDocumentRegistry(array $config): DocumentRegistry
    {
        $registry = new DocumentRegistry();

        foreach ($config['documents'] as $index => $docData) {
            if (!isset($docData['description'], $docData['outputPath'])) {
                throw new \RuntimeException(
                    sprintf('Document at index %d must have "description" and "outputPath"', $index),
                );
            }

            $document = Document::create(
                description: (string) $docData['description'],
                outputPath: (string) $docData['outputPath'],
            );

            if (isset($docData['sources']) && \is_array($docData['sources'])) {
                foreach ($docData['sources'] as $sourceIndex => $sourceData) {
                    $source = $this->createSource($sourceData, "$index.$sourceIndex");
                    if ($source !== null) {
                        $document->addSource($source);
                    }
                }
            }

            $registry->register($document);
        }

        return $registry;
    }

    /**
     * Create a Source object from its configuration.
     */
    private function createSource(array $sourceData, string $path): ?object
    {
        if (!isset($sourceData['type'])) {
            throw new \RuntimeException(
                \sprintf('Source at path %s must have a "type" property', $path),
            );
        }

        $description = $sourceData['description'] ?? '';

        return match ($sourceData['type']) {
            'file' => $this->createFileSource($sourceData, $path, $description),
            'php_class' => $this->createPhpClassSource($sourceData, $path, $description),
            'url' => $this->createUrlSource($sourceData, $path, $description),
            'text' => $this->createTextSource($sourceData, $path, $description),
            default => throw new \RuntimeException(
                \sprintf('Unknown source type "%s" at path %s', $sourceData['type'], $path),
            ),
        };
    }

    /**
     * Create a FileSource from its configuration.
     */
    private function createPhpClassSource(array $data, string $path, string $description): PhpClassSource
    {
        if (!isset($data['sourcePaths'])) {
            throw new \RuntimeException(
                \sprintf('File source at path %s must have a "sourcePaths" property', $path),
            );
        }

        $sourcePaths = $data['sourcePaths'];

        if (!\is_string($sourcePaths) && !\is_array($sourcePaths)) {
            throw new \RuntimeException(
                \sprintf('"sourcePaths" must be a string or array in source at path %s', $path),
            );
        }

        $sourcePaths = \is_string($sourcePaths) ? [$sourcePaths] : $sourcePaths;
        $sourcePaths = \array_map(
            fn(string $sourcePath): string => $this->rootPath . '/' . \trim($sourcePath, '/'),
            $sourcePaths,
        );

        return new PhpClassSource(
            sourcePaths: $sourcePaths,
            description: $description,
            excludePatterns: $data['excludePatterns'] ?? [],
            showTreeView: $data['showTreeView'] ?? true,
            onlySignatures: $data['onlySignatures'] ?? true,
        );
    }

    /**
     * Create a FileSource from its configuration.
     */
    private function createFileSource(array $data, string $path, string $description): FileSource
    {
        if (!isset($data['sourcePaths'])) {
            throw new \RuntimeException(
                \sprintf('File source at path %s must have a "sourcePaths" property', $path),
            );
        }

        $sourcePaths = $data['sourcePaths'];

        if (!\is_string($sourcePaths) && !\is_array($sourcePaths)) {
            throw new \RuntimeException(
                \sprintf('"sourcePaths" must be a string or array in source at path %s', $path),
            );
        }

        $sourcePaths = \is_string($sourcePaths) ? [$sourcePaths] : $sourcePaths;
        $sourcePaths = \array_map(
            fn(string $sourcePath): string => $this->rootPath . '/' . \trim($sourcePath, '/'),
            $sourcePaths,
        );

        return new FileSource(
            sourcePaths: $sourcePaths,
            description: $description,
            filePattern: $data['filePattern'] ?? '*.*',
            excludePatterns: $data['excludePatterns'] ?? [],
            showTreeView: $data['showTreeView'] ?? true,
        );
    }

    /**
     * Create a UrlSource from its configuration.
     */
    private function createUrlSource(array $data, string $path, string $description): UrlSource
    {
        if (!isset($data['urls']) || !\is_array($data['urls'])) {
            throw new \RuntimeException(
                \sprintf('URL source at path %s must have a "urls" array property', $path),
            );
        }

        return new UrlSource(
            urls: $data['urls'],
            description: $description,
            selector: $data['selector'] ?? null,
        );
    }

    /**
     * Create a TextSource from its configuration.
     */
    private function createTextSource(array $data, string $path, string $description): TextSource
    {
        if (!isset($data['content']) || !\is_string($data['content'])) {
            throw new \RuntimeException(
                \sprintf('Text source at path %s must have a "content" string property', $path),
            );
        }

        return new TextSource(
            content: $data['content'],
            description: $description,
        );
    }

    public function isSupported(): bool
    {
        return \file_exists($this->configPath) && \pathinfo($this->configPath, PATHINFO_EXTENSION) === 'json';
    }
}
