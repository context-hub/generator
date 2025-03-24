<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\ConfigLoader\Import;

/**
 * Represents a configuration import directive
 */
final readonly class ImportConfig
{
    public function __construct(
        public string $path,
        public string $absolutePath,
        public ?string $pathPrefix = null,
    ) {}

    /**
     * Create from an array configuration
     */
    public static function fromArray(array $config, string $basePath): self
    {
        // Handle object format with path and optional pathPrefix
        if (!isset($config['path'])) {
            throw new \InvalidArgumentException("Import configuration must have a 'path' property");
        }
        $path = $config['path'];
        $pathPrefix = $config['pathPrefix'] ?? null;

        // Resolve relative path to absolute path
        $absolutePath = self::resolvePath($path, $basePath);

        return new self(
            path: $path,
            absolutePath: $absolutePath,
            pathPrefix: $pathPrefix,
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
