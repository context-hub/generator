<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Loader;

use Butschster\ContextGenerator\Document;
use Butschster\ContextGenerator\DocumentRegistry;
use Butschster\ContextGenerator\DocumentsLoaderInterface;
use Butschster\ContextGenerator\FilesInterface;
use Butschster\ContextGenerator\Source\FileSource;
use Butschster\ContextGenerator\Source\GithubSource;
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
                overwrite: (bool) ($docData['overwrite'] ?? true),
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
            'url' => $this->createUrlSource($sourceData, $path, $description),
            'text' => $this->createTextSource($sourceData, $path, $description),
            'github' => $this->createGithubSource($sourceData, $path, $description),
            default => throw new \RuntimeException(
                \sprintf('Unknown source type "%s" at path %s', $sourceData['type'], $path),
            ),
        };
    }

    /**
     * Parse modifiers configuration
     *
     * @param array<mixed> $modifiersConfig
     * @return array<string|array{name: string, options: array<string, mixed>}>
     */
    private function parseModifiers(array $modifiersConfig): array
    {
        $result = [];

        foreach ($modifiersConfig as $modifier) {
            if (\is_string($modifier)) {
                $result[] = $modifier;
            } elseif (\is_array($modifier) && isset($modifier['name'])) {
                $result[] = [
                    'name' => $modifier['name'],
                    'options' => $modifier['options'] ?? [],
                ];
            } else {
                throw new \InvalidArgumentException(
                    sprintf('Invalid modifier format: %s', json_encode($modifier)),
                );
            }
        }

        return $result;
    }

    /**
     * Create a GithubSource from its configuration.
     */
    private function createGithubSource(
        array $data,
        string $path,
        string $description,
    ): GithubSource {
        if (!isset($data['repository'])) {
            throw new \RuntimeException(
                \sprintf('GitHub source at path %s must have a "repository" property', $path),
            );
        }

        // Determine source paths (required)
        if (!isset($data['sourcePaths'])) {
            throw new \RuntimeException(
                \sprintf('GitHub source at path %s must have a "sourcePaths" property', $path),
            );
        }

        $sourcePaths = $data['sourcePaths'];
        if (!\is_string($sourcePaths) && !\is_array($sourcePaths)) {
            throw new \RuntimeException(
                \sprintf('"sourcePaths" must be a string or array in source at path %s', $path),
            );
        }

        // Convert to array if single string
        $sourcePaths = \is_string($sourcePaths) ? [$sourcePaths] : $sourcePaths;

        return new GithubSource(
            repository: $data['repository'],
            sourcePaths: $sourcePaths,
            branch: $data['branch'] ?? 'main',
            description: $description,
            filePattern: $data['filePattern'] ?? '*.php',
            excludePatterns: $data['excludePatterns'] ?? [],
            showTreeView: $data['showTreeView'] ?? true,
            githubToken: $this->parseConfigValue($data['githubToken'] ?? null),
            modifiers: $this->parseModifiers($data['modifiers'] ?? []),
        );
    }

    /**
     * Parse a configuration value, resolving environment variables if needed.
     *
     * @param string|null $value The configuration value to parse
     * @return string|null The parsed value
     */
    private function parseConfigValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Check if the value is an environment variable reference
        if (\preg_match('/^\${([A-Za-z0-9_]+)}$/', $value, $matches)) {
            // Get the environment variable name
            $envName = $matches[1];

            // Get the value from environment
            $envValue = getenv($envName);

            // Return the environment variable value or null if not set
            return $envValue !== false ? $envValue : null;
        }

        // Check if the value has embedded environment variables
        if (\preg_match_all('/\${([A-Za-z0-9_]+)}/', $value, $matches)) {
            // Replace all environment variables in the string
            foreach ($matches[0] as $index => $placeholder) {
                $envName = $matches[1][$index];
                $envValue = \getenv($envName);

                if ($envValue !== false) {
                    $value = \str_replace($placeholder, $envValue, $value);
                }
            }
        }

        return $value;
    }

    /**
     * Create a FileSource from its configuration.
     */
    private function createFileSource(
        array $data,
        string $path,
        string $description,
    ): FileSource {
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
            modifiers: $this->parseModifiers($data['modifiers'] ?? []),
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
