# Stage 2: ProjectConfig Enhancement

## Overview

Extend the `ProjectConfig` class to support path-based project definitions alongside existing alias-based projects. This stage adds `path` and `resolvedPath` properties, updates the factory method, and adds helper methods for distinguishing between path-based and alias-based projects.

After this stage, `ProjectConfig` can represent both types of projects while maintaining full backward compatibility.

## Files

**MODIFY**:
- `src/McpServer/Project/ProjectConfig.php` — Add path support
- `tests/src/Unit/McpServer/Project/ProjectConfigTest.php` — Extend with path tests

## Code References

### Current ProjectConfig Structure
```
src/McpServer/Project/ProjectConfig.php:1-50
```
Current implementation has only `name` and `description`. We add `path` and `resolvedPath` while keeping the existing API working.

### Test Patterns
```
tests/src/Unit/McpServer/Project/ProjectConfigTest.php:1-90
```
Follow existing test structure — use `#[Test]` attributes, descriptive method names.

### Similar DTO Pattern
```
vendor/ctx/mcp-server/src/Projects/DTO/ProjectDTO.php:1-50
```
Shows pattern for optional properties with null defaults.

## Implementation Details

### Updated ProjectConfig

```php
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
```

### Key Changes

1. **New properties**: `path` (raw from YAML) and `resolvedPath` (absolute)
2. **Updated `fromArray()`**: Accepts `resolvedPath` parameter, extracts `path` from data
3. **New `isPathBased()`**: Returns true if project has path (not alias-based)
4. **New `withResolvedPath()`**: Immutable setter for resolved path
5. **Updated `jsonSerialize()`**: Includes new fields when present

### Backward Compatibility

- Constructor defaults new parameters to `null`
- `fromArray()` still works with old data format (name + description only)
- Existing tests continue to pass without modification

## Definition of Done

- [ ] `ProjectConfig` has `path` property (nullable string)
- [ ] `ProjectConfig` has `resolvedPath` property (nullable string)
- [ ] `fromArray()` extracts `path` from input array
- [ ] `fromArray()` accepts optional `resolvedPath` parameter
- [ ] `isPathBased()` returns `true` when path is set
- [ ] `isPathBased()` returns `false` when path is null
- [ ] `withResolvedPath()` creates new instance with resolved path
- [ ] `jsonSerialize()` includes `path` when present
- [ ] `jsonSerialize()` includes `resolvedPath` when present
- [ ] All existing tests pass (backward compatibility)
- [ ] New tests added for:
  - [ ] Constructor with path
  - [ ] `fromArray()` with path
  - [ ] `isPathBased()` true/false cases
  - [ ] `withResolvedPath()` immutability
  - [ ] JSON serialization with paths

## Dependencies

**Requires**: Stage 1 (concepts, though not direct code dependency)

**Enables**: Stage 3 (Parser creates ProjectConfig instances with paths)
