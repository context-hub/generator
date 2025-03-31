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
        if (isset($config['documents']) && \is_array($config['documents'])) {
            foreach ($config['documents'] as &$document) {
                if (isset($document['sources']) && \is_array($document['sources'])) {
                    foreach ($document['sources'] as &$source) {
                        $source = $this->applyPathPrefixToSource($source, $pathPrefix);
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
            $source['sourcePaths'] = $this->applyPrefixToPaths($source['sourcePaths'], $prefix);
        }

        // Handle composerPath property (for composer source)
        if (isset($source['composerPath']) && $source['type'] === 'composer') {
            if (!$this->isAbsolutePath($source['composerPath'])) {
                $source['composerPath'] = $this->combinePaths($prefix, $source['composerPath']);
            }
        }

        return $source;
    }

    /**
     * Apply prefix to path(s)
     */
    private function applyPrefixToPaths(mixed $paths, string $prefix): mixed
    {
        if (\is_string($paths)) {
            // Skip absolute paths
            if ($this->isAbsolutePath($paths)) {
                return $paths;
            }
            return $this->combinePaths($prefix, $paths);
        }

        if (\is_array($paths)) {
            $result = [];
            foreach ($paths as $path) {
                $result[] = $this->applyPrefixToPaths($path, $prefix);
            }
            return $result;
        }

        // If it's not a string or array, return as is
        return $paths;
    }
}
