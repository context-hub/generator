<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Github;

use Butschster\ContextGenerator\Source\Registry\AbstractSourceFactory;
use Butschster\ContextGenerator\Source\SourceInterface;

/**
 * Factory for creating GithubSource instances
 */
final readonly class GithubSourceFactory extends AbstractSourceFactory
{
    #[\Override]
    public function getType(): string
    {
        return 'github';
    }

    #[\Override]
    public function create(array $config): SourceInterface
    {
        $this->logger?->debug('Creating GitHub source', [
            'path' => (string) $this->dirs->getRootPath(),
            'config' => $config,
        ]);

        if (!isset($config['repository'])) {
            throw new \RuntimeException(message: 'GitHub source must have a "repository" property');
        }

        // Determine source paths (required)
        if (!isset($config['sourcePaths'])) {
            throw new \RuntimeException(message: 'GitHub source must have a "sourcePaths" property');
        }
        $sourcePaths = $config['sourcePaths'];
        if (!\is_string(value: $sourcePaths) && !\is_array(value: $sourcePaths)) {
            throw new \RuntimeException(message: '"sourcePaths" must be a string or array in source');
        }

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

        // Convert notPath to match Symfony Finder's naming convention
        $notPath = $config['excludePatterns'] ?? $config['notPath'] ?? [];

        return new GithubSource(
            repository: $config['repository'],
            sourcePaths: $sourcePaths,
            branch: $config['branch'] ?? 'main',
            description: $config['description'] ?? '',
            filePattern: $config['filePattern'] ?? '*.*',
            notPath: $notPath,
            path: $config['path'] ?? null,
            contains: $config['contains'] ?? null,
            notContains: $config['notContains'] ?? null,
            showTreeView: $config['showTreeView'] ?? true,
            githubToken: $config['githubToken'] ?? null,
            modifiers: $config['modifiers'] ?? [],
            tags: $config['tags'] ?? [],
        );
    }
}
