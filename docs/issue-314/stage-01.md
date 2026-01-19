# Stage 1: Path Resolution Infrastructure

## Overview

Create the foundational infrastructure for resolving project paths from YAML configuration. This stage introduces the `ProjectPathException` for error handling and `ProjectPathResolver` for converting relative/absolute paths to validated absolute paths.

This is the foundation that all subsequent stages build upon — without proper path resolution, we cannot support path-based project definitions.

## Files

**CREATE**:
- `src/McpServer/Project/Exception/ProjectPathException.php` — Path-specific exception with factory methods
- `src/McpServer/Project/ProjectPathResolver.php` — Path resolution and validation logic
- `tests/src/Unit/McpServer/Project/ProjectPathResolverTest.php` — Comprehensive unit tests

## Code References

### Exception Pattern
```
src/McpServer/Project/Exception/ProjectNotAvailableException.php
```
Follow this pattern for exception structure — readonly properties, factory methods, clear messages.

### Path Handling
```
src/Application/FSPath.php:1-400
```
Use `FSPath` for all path operations:
- `FSPath::create($path)` — create path object
- `->join($other)` — join paths
- `->isAbsolute()` — check if absolute
- `->toString()` — get string representation

### Validation Pattern
```
vendor/ctx/mcp-server/src/Projects/Repository/ProjectStateRepository.php:45-55
```
Shows pattern for checking file existence with `FilesInterface`.

## Implementation Details

### ProjectPathException

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Project\Exception;

/**
 * Exception thrown when project path resolution fails.
 */
final class ProjectPathException extends \RuntimeException
{
    public function __construct(
        public readonly string $path,
        public readonly string $reason,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function notFound(string $path): self
    {
        return new self(
            path: $path,
            reason: 'not_found',
            message: \sprintf("Project path '%s' does not exist.", $path),
        );
    }

    public static function notDirectory(string $path): self
    {
        return new self(
            path: $path,
            reason: 'not_directory',
            message: \sprintf("Project path '%s' is not a directory.", $path),
        );
    }

    public static function notReadable(string $path): self
    {
        return new self(
            path: $path,
            reason: 'not_readable',
            message: \sprintf("Project path '%s' is not readable.", $path),
        );
    }
}
```

### ProjectPathResolver

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Project;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\McpServer\Project\Exception\ProjectPathException;
use Psr\Log\LoggerInterface;

/**
 * Resolves and validates project paths from YAML configuration.
 *
 * Handles both relative paths (resolved from context file directory)
 * and absolute paths (used directly after validation).
 */
final readonly class ProjectPathResolver
{
    public function __construct(
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Resolve a path to an absolute, validated path.
     *
     * @param string $path Raw path from YAML (relative or absolute)
     * @param string $contextDir Directory containing context.yaml
     * @return string Resolved absolute path
     * @throws ProjectPathException If path is invalid
     */
    public function resolve(string $path, string $contextDir): string
    {
        $this->logger?->debug('Resolving project path', [
            'path' => $path,
            'contextDir' => $contextDir,
        ]);

        // Create FSPath for the input
        $fsPath = FSPath::create($path);

        // Resolve relative paths against context directory
        if ($fsPath->isRelative()) {
            $contextPath = FSPath::create($contextDir);
            $fsPath = $contextPath->join($path);
            
            $this->logger?->debug('Resolved relative path', [
                'original' => $path,
                'resolved' => $fsPath->toString(),
            ]);
        }

        $resolvedPath = $fsPath->toString();

        // Validate the resolved path
        $this->validate($resolvedPath);

        $this->logger?->info('Project path resolved successfully', [
            'path' => $resolvedPath,
        ]);

        return $resolvedPath;
    }

    /**
     * Validate that a path exists, is a directory, and is readable.
     *
     * @throws ProjectPathException If validation fails
     */
    private function validate(string $path): void
    {
        if (!\file_exists($path)) {
            throw ProjectPathException::notFound($path);
        }

        if (!\is_dir($path)) {
            throw ProjectPathException::notDirectory($path);
        }

        if (!\is_readable($path)) {
            throw ProjectPathException::notReadable($path);
        }
    }
}
```

### Key Design Decisions

1. **Use FSPath consistently** — All path operations go through FSPath for cross-platform compatibility
2. **Validate eagerly** — Fail fast during config parsing, not during tool execution
3. **Immutable & readonly** — No state, can be shared safely
4. **Optional logger** — Logging helps debug path resolution issues

## Definition of Done

- [ ] `ProjectPathException` created with three factory methods
- [ ] `ProjectPathResolver` resolves relative paths correctly
- [ ] `ProjectPathResolver` handles absolute paths correctly
- [ ] Validation throws appropriate exceptions for:
  - [ ] Non-existent paths
  - [ ] Paths that are files (not directories)
  - [ ] Non-readable paths
- [ ] Unit tests cover all scenarios including:
  - [ ] Relative path resolution
  - [ ] Absolute path passthrough
  - [ ] Path with `..` segments
  - [ ] All three exception types
- [ ] All tests pass: `./vendor/bin/phpunit tests/src/Unit/McpServer/Project/ProjectPathResolverTest.php`

## Dependencies

**Requires**: None (first stage)

**Enables**: Stage 2 (ProjectConfig needs resolver concept), Stage 3 (Parser uses resolver)
