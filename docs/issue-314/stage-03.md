# Stage 3: Parser Plugin Two-Pass Resolution

## Overview

Implement the core resolution algorithm in `ProjectsParserPlugin`. This stage introduces a two-pass approach:

1. **First pass**: Process all path-based projects, resolve paths, register them, track claimed paths
2. **Second pass**: Process alias-based projects, skip if resolved path already claimed

This ensures local path-based definitions always take priority over global aliases pointing to the same physical location.

## Files

**MODIFY**:
- `src/McpServer/Project/ProjectsParserPlugin.php` — Two-pass resolution algorithm
- `src/McpServer/Project/ProjectBootloader.php` — Register ProjectPathResolver

**CREATE**:
- `tests/src/Unit/McpServer/Project/ProjectsParserPluginTest.php` — Resolution tests

## Code References

### Current Parser Implementation
```
src/McpServer/Project/ProjectsParserPlugin.php:45-90
```
Current implementation iterates once, checking aliases. We restructure to two passes with path priority.

### Bootloader Pattern
```
src/McpServer/Project/ProjectBootloader.php:30-55
```
Shows how to register singletons and inject into other services.

### Plugin Testing Pattern
```
tests/src/Unit/McpServer/Project/ProjectWhitelistRegistryTest.php:1-80
```
Shows unit test setup without full app bootstrap.

### Config Parser Flow
```
src/Config/Parser/ConfigParser.php:25-35
```
Shows how `rootPath` is passed to plugins — this is our `contextDir`.

## Implementation Details

### Updated ProjectsParserPlugin

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Project;

use Butschster\ContextGenerator\Config\Parser\ConfigParserPluginInterface;
use Butschster\ContextGenerator\Config\Registry\RegistryInterface;
use Butschster\ContextGenerator\McpServer\Project\Exception\ProjectPathException;
use Butschster\ContextGenerator\McpServer\Projects\ProjectServiceInterface;
use Psr\Log\LoggerInterface;
use Spiral\Core\Attribute\Proxy;

/**
 * Config parser plugin that handles the `projects` section in context.yaml.
 *
 * Resolution algorithm (two-pass):
 * 1. First pass: Process path-based projects, resolve and claim paths
 * 2. Second pass: Process alias-based projects, skip if path already claimed
 *
 * This ensures local path-based definitions take priority over global aliases.
 */
final readonly class ProjectsParserPlugin implements ConfigParserPluginInterface
{
    public function __construct(
        private ProjectWhitelistRegistry $registry,
        #[Proxy] private ProjectServiceInterface $projectService,
        private ProjectPathResolver $pathResolver,
        private ?LoggerInterface $logger = null,
    ) {}

    public function getConfigKey(): string
    {
        return 'projects';
    }

    public function supports(array $config): bool
    {
        return isset($config['projects']) && \is_array($config['projects']);
    }

    public function updateConfig(array $config, string $rootPath): array
    {
        return $config;
    }

    public function parse(array $config, string $rootPath): ?RegistryInterface
    {
        if (!$this->supports($config)) {
            return null;
        }

        $this->logger?->debug('Parsing projects configuration', [
            'projectCount' => \count($config['projects']),
            'rootPath' => $rootPath,
        ]);

        // Track claimed paths (resolvedPath => projectName)
        $claimedPaths = [];

        // First pass: Process path-based projects (local priority)
        $claimedPaths = $this->processPathBasedProjects(
            $config['projects'],
            $rootPath,
            $claimedPaths,
        );

        // Second pass: Process alias-based projects (skip if path claimed)
        $this->processAliasBasedProjects(
            $config['projects'],
            $claimedPaths,
        );

        return null;
    }

    /**
     * First pass: Process projects with 'path' field.
     *
     * @param array $projects Raw project configurations
     * @param string $rootPath Context file directory
     * @param array<string, string> $claimedPaths Already claimed paths
     * @return array<string, string> Updated claimed paths (path => name)
     */
    private function processPathBasedProjects(
        array $projects,
        string $rootPath,
        array $claimedPaths,
    ): array {
        foreach ($projects as $projectData) {
            if (!\is_array($projectData)) {
                continue;
            }

            // Skip if no path field (alias-based)
            if (!isset($projectData['path']) || $projectData['path'] === '') {
                continue;
            }

            $name = $projectData['name'] ?? null;
            if ($name === null || $name === '') {
                $this->logger?->warning('Path-based project missing name', [
                    'data' => $projectData,
                ]);
                continue;
            }

            try {
                // Resolve the path
                $resolvedPath = $this->pathResolver->resolve(
                    $projectData['path'],
                    $rootPath,
                );

                // Check if this path is already claimed
                if (isset($claimedPaths[$resolvedPath])) {
                    $this->logger?->warning('Path already claimed by another project', [
                        'path' => $resolvedPath,
                        'existingProject' => $claimedPaths[$resolvedPath],
                        'skippedProject' => $name,
                    ]);
                    continue;
                }

                // Create and register the project
                $projectConfig = ProjectConfig::fromArray($projectData, $resolvedPath);
                
                if ($projectConfig === null) {
                    continue;
                }

                $this->registry->register($projectConfig);
                $claimedPaths[$resolvedPath] = $name;

                $this->logger?->info('Registered path-based project', [
                    'name' => $name,
                    'path' => $projectData['path'],
                    'resolvedPath' => $resolvedPath,
                ]);

            } catch (ProjectPathException $e) {
                $this->logger?->warning('Failed to resolve project path', [
                    'name' => $name,
                    'path' => $projectData['path'],
                    'error' => $e->getMessage(),
                    'reason' => $e->reason,
                ]);
            }
        }

        return $claimedPaths;
    }

    /**
     * Second pass: Process projects without 'path' field (alias-based).
     *
     * @param array $projects Raw project configurations
     * @param array<string, string> $claimedPaths Paths claimed by path-based projects
     */
    private function processAliasBasedProjects(
        array $projects,
        array $claimedPaths,
    ): void {
        // Get global aliases
        $globalAliases = $this->projectService->getAliases();

        foreach ($projects as $projectData) {
            if (!\is_array($projectData)) {
                continue;
            }

            // Skip if has path field (already processed)
            if (isset($projectData['path']) && $projectData['path'] !== '') {
                continue;
            }

            $name = $projectData['name'] ?? null;
            if ($name === null || $name === '') {
                $this->logger?->warning('Alias-based project missing name', [
                    'data' => $projectData,
                ]);
                continue;
            }

            // Check if alias exists in global registry
            if (!isset($globalAliases[$name])) {
                $this->logger?->debug('Alias not found in global registry', [
                    'name' => $name,
                ]);
                continue;
            }

            $resolvedPath = $globalAliases[$name];

            // Check if this path is already claimed by a path-based project
            if (isset($claimedPaths[$resolvedPath])) {
                $this->logger?->info('Skipping alias - path claimed by local project', [
                    'alias' => $name,
                    'resolvedPath' => $resolvedPath,
                    'claimedBy' => $claimedPaths[$resolvedPath],
                ]);
                continue;
            }

            // Create and register the project
            $projectConfig = ProjectConfig::fromArray($projectData, $resolvedPath);
            
            if ($projectConfig === null) {
                continue;
            }

            $this->registry->register($projectConfig);

            $this->logger?->info('Registered alias-based project', [
                'name' => $name,
                'resolvedPath' => $resolvedPath,
            ]);
        }
    }
}
```

### Updated ProjectBootloader

Add `ProjectPathResolver` to singletons:

```php
#[\Override]
public function defineSingletons(): array
{
    return [
        ProjectWhitelistRegistryInterface::class => ProjectWhitelistRegistry::class,
        ProjectWhitelistRegistry::class => ProjectWhitelistRegistry::class,
        ProjectPathResolver::class => ProjectPathResolver::class,  // ADD THIS
        ProjectsParserPlugin::class => ProjectsParserPlugin::class,
        ProjectInterceptor::class => ProjectInterceptor::class,
    ];
}
```

### Resolution Algorithm Summary

```
Input: projects array from YAML + rootPath (context dir)

Pass 1 - Path-based (LOCAL PRIORITY):
  for each project with 'path':
    resolve path relative to rootPath
    if valid and not claimed:
      register project
      claim path
    else:
      log warning, skip

Pass 2 - Alias-based:
  get global aliases from ProjectService
  for each project without 'path':
    lookup alias in global registry
    if found and path NOT claimed:
      register project
    else:
      skip (local wins)

Output: ProjectWhitelistRegistry populated
```

## Definition of Done

- [ ] `ProjectPathResolver` injected into `ProjectsParserPlugin`
- [ ] First pass processes all path-based projects
- [ ] First pass tracks claimed paths correctly
- [ ] Second pass processes alias-based projects
- [ ] Second pass skips projects with already-claimed paths
- [ ] `ProjectBootloader` registers `ProjectPathResolver`
- [ ] Logging added for all resolution decisions
- [ ] Unit tests cover:
  - [ ] Path-based project registration
  - [ ] Alias-based project registration
  - [ ] Path override (local over global)
  - [ ] Invalid path handling (logged, skipped)
  - [ ] Mixed configuration (path + alias)
  - [ ] Duplicate path in YAML (first wins)
- [ ] All existing tests pass

## Dependencies

**Requires**: Stage 1 (ProjectPathResolver), Stage 2 (ProjectConfig with path)

**Enables**: Stage 4 (Integration testing)
