<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\File;

use Butschster\ContextGenerator\Lib\TreeBuilder\TreeViewConfig;
use Butschster\ContextGenerator\Source\Registry\AbstractSourceFactory;
use Butschster\ContextGenerator\Source\SourceInterface;

/**
 * Factory for creating FileSource instances
 */
final readonly class FileSourceFactory extends AbstractSourceFactory
{
    #[\Override]
    public function getType(): string
    {
        return 'file';
    }

    #[\Override]
    public function create(array $config): SourceInterface
    {
        $this->logger?->debug('Creating file source', [
            'path' => $this->dirs->getRootPath(),
            'config' => $config,
        ]);

        if (isset($config['modifiers'])) {
            $config['modifiers'] = $this->parseModifiers(modifiersConfig: $config['modifiers']);
        }

        if (!isset($config['sourcePaths'])) {
            throw new \RuntimeException(message: 'File source must have a "sourcePaths" property');
        }

        $sourcePaths = $config['sourcePaths'];
        if (!\is_string(value: $sourcePaths) && !\is_array(value: $sourcePaths)) {
            throw new \RuntimeException(message: '"sourcePaths" must be a string or array in source');
        }

        $sourcePaths = \is_string(value: $sourcePaths) ? [$sourcePaths] : $sourcePaths;
        $sourcePaths = \array_map(
            callback: fn(string $sourcePath): string => (string) $this->dirs->getRootPath()->join($sourcePath),
            array: $sourcePaths,
        );

        // Validate filePattern if present
        if (isset($config['filePattern'])) {
            if (!\is_string(value: $config['filePattern']) && !\is_array(value: $config['filePattern'])) {
                throw new \RuntimeException(message: 'filePattern must be a string or an array of strings');
            }

            // If it's an array, make sure all elements are strings
            if (\is_array(value: $config['filePattern'])) {
                foreach ($config['filePattern'] as $pattern) {
                    if (!\is_string(value: $pattern)) {
                        throw new \RuntimeException(message: 'All elements in filePattern must be strings');
                    }
                }
            }
        }

        // Validate maxFiles if present
        if (isset($config['maxFiles']) && $config['maxFiles'] !== null) {
            if (!\is_int(value: $config['maxFiles'])) {
                throw new \RuntimeException(message: 'maxFiles must be an integer or null');
            }

            if ($config['maxFiles'] < 0) {
                throw new \RuntimeException(message: 'maxFiles cannot be negative');
            }
        }

        // Handle filePattern parameter, allowing both string and array formats
        $filePattern = $config['filePattern'] ?? '*.*';

        // Convert notPath to match Symfony Finder's naming convention
        $notPath = $config['excludePatterns'] ?? $config['notPath'] ?? [];

        // Handle tree view configuration (either boolean or config object)
        return new FileSource(
            sourcePaths: $sourcePaths,
            description: $config['description'] ?? '',
            filePattern: $filePattern,
            notPath: $notPath,
            path: $config['path'] ?? [],
            contains: $config['contains'] ?? [],
            notContains: $config['notContains'] ?? [],
            size: $config['size'] ?? [],
            date: $config['date'] ?? [],
            ignoreUnreadableDirs: $config['ignoreUnreadableDirs'] ?? false,
            treeView: TreeViewConfig::fromArray(data: $config),
            maxFiles: $config['maxFiles'] ?? 0,
            modifiers: $config['modifiers'] ?? [],
            tags: $config['tags'] ?? [],
        );
    }
}
