<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import;

/**
 * Represents a configuration import directive
 */
final readonly class ImportConfig
{
    /**
     * Source type constants
     */
    public const string TYPE_LOCAL = 'local';

    public const string TYPE_GITHUB = 'github';
    public const string TYPE_URL = 'url';
    public const string TYPE_COMPOSER = 'composer';

    /**
     * Creates a new ImportConfig instance.
     *
     * @param string $path The path to the configuration file to import
     * @param string $absolutePath The resolved absolute path to the import file
     * @param string $type The type of import source (local, github, url, composer)
     * @param string|null $pathPrefix An optional prefix to add to the output path for documents from this import
     * @param bool $hasWildcard Whether the path contains wildcard characters for pattern matching
     * @param array|null $docs Selective list of documents to import (null means import all)
     * @param array $extra Extra configuration parameters specific to the source type
     */
    public function __construct(
        public string $path,
        public string $absolutePath,
        public string $type = self::TYPE_LOCAL,
        public ?string $pathPrefix = null,
        public bool $hasWildcard = false,
        public ?array $docs = null,
        public array $extra = [],
    ) {}

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
     * // GitHub import
     * $config = ImportConfig::fromArray(
     *     [
     *         'path' => 'owner/repo/path/to/file.yaml',
     *         'type' => 'github',
     *         'ref' => 'main',
     *         'token' => '${GITHUB_TOKEN}'
     *     ],
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

        // Determine the import type (default to local)
        $type = $config['type'] ?? self::TYPE_LOCAL;

        // Extract the selective document list if specified
        $docs = $config['docs'] ?? null;

        // Extract extra parameters based on source type
        $extra = [];

        // Add source-specific parameters to extra
        switch ($type) {
            case self::TYPE_GITHUB:
                if (isset($config['ref'])) {
                    $extra['ref'] = $config['ref'];
                }
                if (isset($config['token'])) {
                    $extra['token'] = $config['token'];
                }
                break;

            case self::TYPE_URL:
                if (isset($config['headers']) && \is_array($config['headers'])) {
                    $extra['headers'] = $config['headers'];
                }
                break;
        }

        // For all source types, copy any additional parameters
        foreach ($config as $key => $value) {
            if (!\in_array($key, ['path', 'pathPrefix', 'type', 'docs']) && !isset($extra[$key])) {
                $extra[$key] = $value;
            }
        }

        // Check if the path contains wildcards (only relevant for local imports)
        $hasWildcard = $type === self::TYPE_LOCAL && PathMatcher::containsWildcard($path);

        // Resolve relative path to absolute path (only for local imports)
        // For external sources, we'll use the path as provided
        $absolutePath = $type === self::TYPE_LOCAL
            ? self::resolvePath($path, $basePath)
            : $path;

        return new self(
            path: $path,
            absolutePath: $absolutePath,
            type: $type,
            pathPrefix: $pathPrefix,
            hasWildcard: $hasWildcard,
            docs: $docs,
            extra: $extra,
        );
    }

    public function configDirectory(bool $absolute = false): string
    {
        return \dirname($absolute ? $this->absolutePath : $this->path);
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
