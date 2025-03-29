<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\ConfigLoader\Import;

/**
 * Represents a configuration import directive
 */
final readonly class ImportConfig
{
    /**
     * Creates a new ImportConfig instance.
     *
     * @param string $path The path to the configuration file to import
     * @param string $absolutePath The resolved absolute path to the import file
     * @param string|null $pathPrefix An optional prefix to add to the output path for documents from this import
     * @param bool $hasWildcard Whether the path contains wildcard characters for pattern matching
     */
    public function __construct(
        public string $path,
        public string $absolutePath,
        public ?string $pathPrefix = null,
        public bool $hasWildcard = false,
    ) {}

    public function configDirectory(bool $absolute = false): string
    {
        return \dirname($absolute ? $this->absolutePath : $this->path);
    }

    /**
     * Create from an array configuration
     *
     * Example:
     * ```php
     * // Simple import with no prefix
     * $config = ImportConfig::fromArray(['path' => 'configs/api.json'], '/app');
     *
     * // Import with output path prefix
     * $config = ImportConfig::fromArray(
     *     ['path' => 'configs/api.json', 'pathPrefix' => 'api/v1'],
     *     '/app'
     * );
     *
     * // With this configuration, documents from api.json will be
     * // generated with the path prefix 'api/v1/' in the output directory
     * ```
     *
     * @param array $config Import configuration array with 'path' and optional 'pathPrefix'
     * @param string $basePath Base path for resolving relative import paths
     * @throws \InvalidArgumentException If required 'path' property is missing
     */
    public static function fromArray(array $config, string $basePath): self
    {
        // Handle object format with path and optional pathPrefix
        if (!isset($config['path'])) {
            throw new \InvalidArgumentException("Import configuration must have a 'path' property");
        }
        $path = $config['path'];

        // The pathPrefix is used to specify a subdirectory in the output path
        // where documents from this import should be generated
        $pathPrefix = $config['pathPrefix'] ?? null;

        // Check if the path contains wildcards
        $hasWildcard = PathMatcher::containsWildcard($path);

        // Resolve relative path to absolute path
        // Note: For wildcard paths, this will be used as a base path for pattern matching
        $absolutePath = self::resolvePath($path, $basePath);

        return new self(
            path: $path,
            absolutePath: $absolutePath,
            pathPrefix: $pathPrefix,
            hasWildcard: $hasWildcard,
        );
    }

    /**
     * Resolve a relative path to an absolute path
     */
    private static function resolvePath(string $path, string $basePath): string
    {
        // If it's an absolute path, use it directly
        if (\str_starts_with($path, '/')) {
            return $path;
        }

        // Otherwise, resolve it relative to the base path
        return \rtrim($basePath, '/') . '/' . $path;
    }
}
