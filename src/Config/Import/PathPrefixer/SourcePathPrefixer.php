<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import\PathPrefixer;

/**
 * Applies path prefix to source paths
 *
 * This prefixer handles various source path formats including:
 * - sourcePaths property (string or array)
 * - composerPath property (for composer source type)
 */
final readonly class SourcePathPrefixer extends PathPrefixer
{
    /**
     * Apply path prefix to relevant source paths
     */
    public function applyPrefix(array $config, string $pathPrefix): array
    {
        // Apply to documents array
        if (isset($config['documents']) && \is_array(value: $config['documents'])) {
            foreach ($config['documents'] as &$document) {
                if (isset($document['sources']) && \is_array(value: $document['sources'])) {
                    foreach ($document['sources'] as &$source) {
                        $source = $this->applyPathPrefixToSource(source: $source, prefix: $pathPrefix);
                    }
                }
            }
        }

        return $config;
    }

    /**
     * Apply path prefix to a source configuration
     */
    private function applyPathPrefixToSource(array $source, string $prefix): array
    {
        // Handle sourcePaths property
        if (isset($source['sourcePaths'])) {
            $source['sourcePaths'] = $this->applyPrefixToPaths(paths: $source['sourcePaths'], prefix: $prefix);
        }

        // Handle composerPath property (for composer source)
        if (isset($source['composerPath']) && $source['type'] === 'composer') {
            if (!$this->isAbsolutePath(path: $source['composerPath'])) {
                $source['composerPath'] = $this->combinePaths(prefix: $prefix, path: $source['composerPath']);
            }
        }

        return $source;
    }

    /**
     * Apply prefix to path(s)
     */
    private function applyPrefixToPaths(mixed $paths, string $prefix): mixed
    {
        if (\is_string(value: $paths)) {
            // Skip absolute paths
            if ($this->isAbsolutePath(path: $paths)) {
                return $paths;
            }
            return $this->combinePaths(prefix: $prefix, path: $paths);
        }

        if (\is_array(value: $paths)) {
            $result = [];
            foreach ($paths as $path) {
                $result[] = $this->applyPrefixToPaths(paths: $path, prefix: $prefix);
            }
            return $result;
        }

        // If it's not a string or array, return as is
        return $paths;
    }
}
