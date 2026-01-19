<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Project;

/**
 * Configuration for a whitelisted project.
 *
 * Supports two modes:
 * - Alias-based: References a globally registered project by name
 * - Path-based: Defines a project inline with a relative or absolute path
 */
final readonly class ProjectConfig implements \JsonSerializable
{
    public function __construct(
        /**
         * Project name/identifier used in MCP tools.
         */
        public string $name,
        /**
         * Optional description to help AI understand the project's purpose.
         */
        public ?string $description = null,
        /**
         * Raw path from YAML (relative or absolute).
         * Null for alias-based projects.
         */
        public ?string $path = null,
        /**
         * Resolved absolute path.
         * For path-based: resolved from path field.
         * For alias-based: resolved from global aliases (set by parser).
         */
        public ?string $resolvedPath = null,
    ) {}

    /**
     * Create a ProjectConfig from an array (typically from YAML parsing).
     *
     * @param array{name?: string, description?: string|null, path?: string|null} $data
     * @param string|null $resolvedPath Pre-resolved absolute path (if available)
     */
    public static function fromArray(array $data, ?string $resolvedPath = null): ?self
    {
        $name = $data['name'] ?? null;

        if ($name === null || $name === '') {
            return null;
        }

        return new self(
            name: $name,
            description: $data['description'] ?? null,
            path: $data['path'] ?? null,
            resolvedPath: $resolvedPath,
        );
    }

    /**
     * Check if this project was defined with a path (vs alias).
     */
    public function isPathBased(): bool
    {
        return $this->path !== null;
    }

    /**
     * Create a copy with a resolved path set.
     */
    public function withResolvedPath(string $resolvedPath): self
    {
        return new self(
            name: $this->name,
            description: $this->description,
            path: $this->path,
            resolvedPath: $resolvedPath,
        );
    }

    public function jsonSerialize(): array
    {
        return \array_filter([
            'name' => $this->name,
            'description' => $this->description,
            'path' => $this->path,
            'resolvedPath' => $this->resolvedPath,
        ], static fn($value) => $value !== null);
    }
}
