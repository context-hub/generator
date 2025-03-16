<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Loader\ConfigRegistry;

use Butschster\ContextGenerator\Document\Document;
use Butschster\ContextGenerator\Loader\ConfigRegistry\Parser\ConfigParserPluginInterface;
use Butschster\ContextGenerator\Modifier\Modifier;
use Butschster\ContextGenerator\Source\File\FileSource;
use Butschster\ContextGenerator\Source\GitDiff\CommitDiffSource;
use Butschster\ContextGenerator\Source\Github\GithubSource;
use Butschster\ContextGenerator\Source\Text\TextSource;
use Butschster\ContextGenerator\Source\Url\UrlSource;
use Butschster\ContextGenerator\SourceInterface;

/**
 * Plugin for parsing the "documents" section of the configuration
 */
final readonly class DocumentsParserPlugin implements ConfigParserPluginInterface
{
    public function getConfigKey(): string
    {
        return 'documents';
    }

    public function supports(array $config): bool
    {
        return isset($config['documents']) && \is_array($config['documents']);
    }

    public function parse(array $config, string $rootPath): ?RegistryInterface
    {
        if (!$this->supports($config)) {
            return null;
        }

        $registry = new DocumentRegistry();

        foreach ($config['documents'] as $index => $docData) {
            if (!isset($docData['description'], $docData['outputPath'])) {
                throw new \RuntimeException(
                    \sprintf('Document at index %d must have "description" and "outputPath"', $index),
                );
            }

            // Parse document modifiers if present
            $documentModifiers = [];
            if (isset($docData['modifiers']) && \is_array($docData['modifiers'])) {
                $documentModifiers = $this->parseModifiers($docData['modifiers']);
            }

            $document = Document::create(
                description: (string) $docData['description'],
                outputPath: (string) $docData['outputPath'],
                overwrite: (bool) ($docData['overwrite'] ?? true),
                modifiers: $documentModifiers,
            );

            if (isset($docData['sources']) && \is_array($docData['sources'])) {
                foreach ($docData['sources'] as $sourceIndex => $sourceData) {
                    $source = $this->createSource($sourceData, "$index.$sourceIndex", $rootPath);
                    $document->addSource($source);
                }
            }

            $registry->register($document);
        }

        return $registry;
    }

    /**
     * Create a Source object from its configuration.
     */
    private function createSource(
        array $sourceData,
        string $path,
        string $rootPath,
    ): SourceInterface {
        if (!isset($sourceData['type'])) {
            throw new \RuntimeException(
                \sprintf('Source at path %s must have a "type" property', $path),
            );
        }

        return match ($sourceData['type']) {
            'file' => $this->createFileSource($sourceData, $rootPath),
            'url' => $this->createUrlSource($sourceData),
            'text' => $this->createTextSource($sourceData),
            'github' => $this->createGithubSource($sourceData),
            'git_diff' => $this->createCommitDiffSource($sourceData, $rootPath),
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
    private function createGithubSource(array $data): GithubSource
    {
        if (isset($data['githubToken'])) {
            $data['githubToken'] = ConfigParser::parseConfigValue($data['githubToken']);
        }

        return GithubSource::fromArray($data);
    }

    /**
     * Create a FileSource from its configuration.
     */
    private function createFileSource(array $data, string $rootPath): FileSource
    {
        if (isset($data['modifiers'])) {
            $data['modifiers'] = $this->parseModifiers($data['modifiers']);
        }

        return FileSource::fromArray($data, $rootPath);
    }

    /**
     * Create a UrlSource from its configuration.
     */
    private function createUrlSource(array $data): UrlSource
    {
        return UrlSource::fromArray($data);
    }

    /**
     * Create a TextSource from its configuration.
     */
    private function createTextSource(array $data): TextSource
    {
        return TextSource::fromArray($data);
    }

    /**
     * Create a GitCommitDiffSource from its configuration.
     */
    private function createCommitDiffSource(array $data, string $rootPath): CommitDiffSource
    {
        return CommitDiffSource::fromArray($data, $rootPath);
    }
}
