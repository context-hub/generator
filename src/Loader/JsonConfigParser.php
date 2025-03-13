<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Loader;

use Butschster\ContextGenerator\Document;
use Butschster\ContextGenerator\DocumentRegistry;
use Butschster\ContextGenerator\Modifier\Modifier;
use Butschster\ContextGenerator\Source\File\FileSource;
use Butschster\ContextGenerator\Source\GitDiff\CommitDiffSource;
use Butschster\ContextGenerator\Source\Github\GithubSource;
use Butschster\ContextGenerator\Source\Text\TextSource;
use Butschster\ContextGenerator\Source\Url\UrlSource;

final readonly class JsonConfigParser
{
    public function __construct(
        private string $rootPath,
    ) {}

    /**
     * Create a DocumentRegistry from the decoded JSON configuration.
     */
    public function parse(array $config): DocumentRegistry
    {
        $registry = new DocumentRegistry();

        foreach ($config['documents'] as $index => $docData) {
            if (!isset($docData['description'], $docData['outputPath'])) {
                throw new \RuntimeException(
                    \sprintf('Document at index %d must have "description" and "outputPath"', $index),
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
                    $document->addSource($source);
                }
            }

            $registry->register($document);
        }

        return $registry;
    }

    /**
     * Create a Source object from its configuration.
     *
     */
    private function createSource(
        array $sourceData,
        string $path,
    ): FileSource|UrlSource|TextSource|GithubSource|CommitDiffSource {
        if (!isset($sourceData['type'])) {
            throw new \RuntimeException(
                \sprintf('Source at path %s must have a "type" property', $path),
            );
        }

        return match ($sourceData['type']) {
            'file' => $this->createFileSource($sourceData, $path),
            'url' => $this->createUrlSource($sourceData, $path),
            'text' => $this->createTextSource($sourceData, $path),
            'github' => $this->createGithubSource($sourceData, $path),
            'git_diff' => $this->createCommitDiffSource($sourceData, $path),
            default => throw new \RuntimeException(
                \sprintf('Unknown source type "%s" at path %s', $sourceData['type'], $path),
            ),
        };
    }

    /**
     * Parse modifiers configuration
     *
     * @param array<mixed> $modifiersConfig
     * @return array<Modifier>
     */
    private function parseModifiers(array $modifiersConfig): array
    {
        $result = [];

        foreach ($modifiersConfig as $modifier) {
            if (\is_string($modifier)) {
                $result[] = new Modifier(name: $modifier);
            } elseif (\is_array($modifier) && isset($modifier['name'])) {
                $result[] = Modifier::from($modifier);
            } else {
                throw new \InvalidArgumentException(
                    \sprintf('Invalid modifier format: %s', \json_encode($modifier)),
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
    ): GithubSource {
        if (isset($data['githubToken'])) {
            $data['githubToken'] = $this->parseConfigValue($data['githubToken']);
        }

        return GithubSource::fromArray($data);
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
            $envValue = \getenv($envName);

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
    private function createFileSource(array $data, string $path): FileSource
    {
        if (isset($data['modifiers'])) {
            $data['modifiers'] = $this->parseModifiers($data['modifiers']);
        }

        return FileSource::fromArray($data, $this->rootPath);
    }

    /**
     * Create a UrlSource from its configuration.
     */
    private function createUrlSource(array $data, string $path): UrlSource
    {
        return UrlSource::fromArray($data);
    }

    /**
     * Create a TextSource from its configuration.
     */
    private function createTextSource(array $data, string $path): TextSource
    {
        return TextSource::fromArray($data);
    }

    /**
     * Create a GitCommitDiffSource from its configuration.
     */
    private function createCommitDiffSource(array $data, string $path): CommitDiffSource
    {
        return CommitDiffSource::fromArray($data, $this->rootPath);
    }
}
