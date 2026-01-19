# Feature: Project Resolution via Relative/Absolute Paths in YAML Context

## Overview

Enable inline project definition in YAML context files using `path` field (absolute or relative to the context file location), eliminating the need for global project registration via `ctx project:add`.

**Key Capabilities**:
- Define projects with `path` field in YAML (relative or absolute)
- Local path-based projects take priority over global aliases
- Same resolved path with different names → local definition wins
- Full backward compatibility with existing alias-based projects

**Example**:
```yaml
projects:
  # Path-based (no registration needed)
  - name: shared-lib
    path: ../shared/rag-tools
    
  # Alias-based (existing behavior)
  - name: ctx-mcp-server
```

## Stage Dependencies

```
Stage 1 (Exception) ──┐
                      ├──► Stage 3 (Parser) ──► Stage 4 (Integration) ──► Stage 5 (Docs)
Stage 2 (Config)   ───┘
```

**Linear Flow**: Stage 1 → Stage 2 → Stage 3 → Stage 4 → Stage 5

## Development Progress

### Stage 1: Path Resolution Infrastructure
- [x] 1.1: Create `ProjectPathException` with factory methods
- [x] 1.2: Create `ProjectPathResolver` class skeleton
- [x] 1.3: Implement `resolve()` method with FSPath
- [x] 1.4: Implement path validation (exists, is directory, readable)
- [x] 1.5: Create `ProjectPathResolverTest` with full coverage

**Notes**: All validation checks implemented with factory methods for exceptions.

**Status**: Complete

**Completed**: 2025-01-19 

---

### Stage 2: ProjectConfig Enhancement
- [x] 2.1: Add `path` and `resolvedPath` properties to `ProjectConfig`
- [x] 2.2: Update `fromArray()` to handle `path` field
- [x] 2.3: Add `isPathBased()` method
- [x] 2.4: Update `jsonSerialize()` to include path fields
- [x] 2.5: Extend `ProjectConfigTest` with path-related tests

**Notes**: Added `withResolvedPath()` immutable setter. Full backward compatibility maintained.

**Status**: Complete

**Completed**: 2025-01-19 

---

### Stage 3: Parser Plugin Two-Pass Resolution
- [x] 3.1: Inject `ProjectPathResolver` into `ProjectsParserPlugin`
- [x] 3.2: Implement first pass: process path-based projects
- [x] 3.3: Implement second pass: process alias-based with claimed path check
- [x] 3.4: Update `ProjectBootloader` to register resolver
- [x] 3.5: Create `ProjectsParserPluginTest` with resolution scenarios

**Notes**: Two-pass algorithm ensures path-based projects take priority. Comprehensive logging added.

**Status**: Complete

**Completed**: 2025-01-19 

---

### Stage 4: Integration Testing
- [x] 4.1: Create test fixture with path-based project structure
- [x] 4.2: Add MCP inspector test for path-based project tools
- [x] 4.3: Add test for local override of global alias
- [x] 4.4: Add test for mixed configuration (path + alias)
- [x] 4.5: Verify no regression in existing functionality

**Notes**: Added 8 new integration tests for path-based projects. All original tests preserved.

**Status**: Complete

**Completed**: 2025-01-19 

---

### Stage 5: Documentation & Cleanup
- [x] 5.1: Update feature request docs with final implementation details
- [x] 5.2: Add YAML configuration examples to docs
- [x] 5.3: Run full test suite and fix any issues
- [x] 5.4: Code cleanup and final review

**Notes**: Updated ctx-docs project (docs/mcp/projects.md) with comprehensive path-based project documentation.

**Status**: Complete

**Completed**: 2025-01-19 

---

## Codebase References

### Core Classes
- `src/McpServer/Project/ProjectConfig.php` — Current project configuration class
- `src/McpServer/Project/ProjectsParserPlugin.php` — Current parser (alias-only)
- `src/McpServer/Project/ProjectWhitelistRegistry.php` — Project storage
- `src/McpServer/Project/ProjectBootloader.php` — DI registration

### Patterns to Follow
- `src/Application/FSPath.php` — Path normalization utility
- `src/McpServer/Project/Exception/ProjectNotAvailableException.php` — Exception pattern
- `src/Config/Parser/ConfigParserPluginInterface.php` — Plugin interface

### Tests to Reference
- `tests/src/Unit/McpServer/Project/ProjectConfigTest.php` — Test patterns
- `tests/src/Unit/McpServer/Project/ProjectWhitelistRegistryTest.php` — Registry tests
- `tests/src/McpInspector/Tools/MultiProjectToolTest.php` — Integration tests

### Vendor (Read-Only Reference)
- `vendor/ctx/mcp-server/src/Projects/ProjectServiceInterface.php` — Global alias API
- `vendor/ctx/mcp-server/src/Projects/ProjectService.php` — Alias resolution

## File Summary

### New Files (4)
| File | Stage | Purpose |
|------|-------|---------|
| `src/McpServer/Project/Exception/ProjectPathException.php` | 1 | Path-specific exceptions |
| `src/McpServer/Project/ProjectPathResolver.php` | 1 | Path resolution logic |
| `tests/src/Unit/McpServer/Project/ProjectPathResolverTest.php` | 1 | Resolver tests |
| `tests/src/Unit/McpServer/Project/ProjectsParserPluginTest.php` | 3 | Parser tests |

### Modified Files (5)
| File | Stage | Changes |
|------|-------|---------|
| `src/McpServer/Project/ProjectConfig.php` | 2 | Add path fields |
| `src/McpServer/Project/ProjectsParserPlugin.php` | 3 | Two-pass resolution |
| `src/McpServer/Project/ProjectBootloader.php` | 3 | Register resolver |
| `tests/src/Unit/McpServer/Project/ProjectConfigTest.php` | 2 | Path tests |
| `tests/src/McpInspector/Tools/MultiProjectToolTest.php` | 4 | Integration tests |

## Usage Instructions

⚠️ **Keep this checklist updated**:
- Mark completed substeps immediately with `[x]`
- Add notes about deviations or challenges
- Document decisions differing from plan
- Update status when starting/completing stages

**Commands**:
```bash
# Run unit tests
./vendor/bin/phpunit --testsuite application-tests

# Run MCP inspector tests
./vendor/bin/phpunit --testsuite mcp-inspector

# Run specific test file
./vendor/bin/phpunit tests/src/Unit/McpServer/Project/ProjectPathResolverTest.php
```
