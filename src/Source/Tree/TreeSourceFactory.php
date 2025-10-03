<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Tree;

use Butschster\ContextGenerator\Lib\TreeBuilder\TreeViewConfig;
use Butschster\ContextGenerator\Source\Registry\AbstractSourceFactory;
use Butschster\ContextGenerator\Source\SourceInterface;

/**
 * Factory for creating TreeSource instances
 */
final readonly class TreeSourceFactory extends AbstractSourceFactory
{
    #[\Override]
    public function getType(): string
    {
        return 'tree';
    }

    #[\Override]
    public function create(array $config): SourceInterface
    {
        $this->logger?->debug('Creating Tree source', [
            'path' => $this->dirs->getRootPath(),
            'config' => $config,
        ]);


        if (!isset($config['sourcePaths'])) {
            throw new \RuntimeException(message: 'Tree source must have a "sourcePaths" property');
        }

        $sourcePaths = $config['sourcePaths'];
        if (!\is_string(value: $sourcePaths) && !\is_array(value: $sourcePaths)) {
            throw new \RuntimeException(message: '"sourcePaths" must be a string or array in source');
        }

        $sourcePaths = \is_string(value: $sourcePaths) ? [$sourcePaths] : $sourcePaths;
        $sourcePaths = \array_map(
            callback: fn(string $sourcePaths): string => (string) $this->dirs->getRootPath()->join($sourcePaths),
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

        // Validate renderFormat if present
        if (isset($config['renderFormat'])) {
            if (!\is_string(value: $config['renderFormat'])) {
                throw new \RuntimeException(message: 'renderFormat must be a string');
            }

            $validFormats = ['ascii'];
            if (!\in_array(needle: $config['renderFormat'], haystack: $validFormats, strict: true)) {
                throw new \RuntimeException(
                    message: \sprintf(
                        'Invalid renderFormat: %s. Allowed formats: %s',
                        $config['renderFormat'],
                        \implode(separator: ', ', array: $validFormats),
                    ),
                );
            }
        }

        // Handle filePattern parameter, allowing both string and array formats
        $filePattern = $config['filePattern'] ?? '*';

        // Convert notPath
        $notPath = $config['notPath'] ?? [];

        // Validate dirContext if present
        if (isset($config['dirContext']) && !\is_array(value: $config['dirContext'])) {
            throw new \RuntimeException(message: 'dirContext must be an associative array');
        }

        return new TreeSource(
            sourcePaths: $sourcePaths,
            description: $config['description'] ?? '',
            filePattern: $filePattern,
            notPath: $notPath,
            path: $config['path'] ?? [],
            contains: $config['contains'] ?? [],
            notContains: $config['notContains'] ?? [],
            renderFormat: $config['renderFormat'] ?? 'ascii',
            treeView: new TreeViewConfig(
                showSize: $config['showSize'] ?? false,
                showLastModified: $config['showLastModified'] ?? false,
                showCharCount: $config['showCharCount'] ?? false,
                includeFiles: $config['includeFiles'] ?? true,
                maxDepth: $config['maxDepth'] ?? 0,
                dirContext: $config['dirContext'] ?? [],
            ),
            tags: $config['tags'] ?? [],
        );
    }
}
