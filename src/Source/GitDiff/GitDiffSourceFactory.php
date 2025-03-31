<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff;

use Butschster\ContextGenerator\Source\GitDiff\RenderStrategy\Config\RenderConfig;
use Butschster\ContextGenerator\Source\Registry\AbstractSourceFactory;
use Butschster\ContextGenerator\Source\SourceInterface;

/**
 * Factory for creating GitDiffSource instances
 */
final readonly class GitDiffSourceFactory extends AbstractSourceFactory
{
    #[\Override]
    public function getType(): string
    {
        return 'git_diff';
    }

    #[\Override]
    public function create(array $config): SourceInterface
    {
        $this->logger?->debug('Creating Git Diff source', [
            'path' => $this->dirs->rootPath,
            'config' => $config,
        ]);

        $repository = $config['repository'] ?? '.';
        if (!\is_string($repository)) {
            throw new \RuntimeException('"repository" must be a string in source');
        }

        // Prepend root path if repository is relative
        $repository = \str_starts_with($repository, '/')
            ? $repository
            : $this->dirs->getFilePath($repository);

        // Validate filePattern if present
        if (isset($config['filePattern'])) {
            if (!\is_string($config['filePattern']) && !\is_array($config['filePattern'])) {
                throw new \RuntimeException('filePattern must be a string or an array of strings');
            }
            // If it's an array, make sure all elements are strings
            if (\is_array($config['filePattern'])) {
                foreach ($config['filePattern'] as $pattern) {
                    if (!\is_string($pattern)) {
                        throw new \RuntimeException('All elements in filePattern must be strings');
                    }
                }
            }
        }

        // Ensure commit is a string
        $commit = $config['commit'] ?? 'staged';
        if (isset($config['commit']) && !\is_string($commit)) {
            throw new \RuntimeException('commit must be a string');
        }

        // Process render configuration - support both legacy and new options
        $renderConfig = new RenderConfig();

        // Check if we have a render property with sub-properties
        if (isset($config['render'])) {
            if (\is_array($config['render'])) {
                $renderConfig = RenderConfig::fromArray($config['render']);
            } elseif (\is_string($config['render'])) {
                $renderConfig = RenderConfig::fromString($config['render']);
            }
        }

        return new GitDiffSource(
            repository: $repository,
            description: $config['description'] ?? '',
            commit: $commit,
            filePattern: $config['filePattern'] ?? '*.*',
            notPath: $config['notPath'] ?? [],
            path: $config['path'] ?? [],
            contains: $config['contains'] ?? [],
            notContains: $config['notContains'] ?? [],
            renderConfig: $renderConfig,
            modifiers: $config['modifiers'] ?? [],
            tags: $config['tags'] ?? [],
        );
    }
}
