# Stage 1: Extend ProjectServiceInterface

## Overview

Add three new method signatures to `ProjectServiceInterface` in the `ctx-mcp-server` library. This establishes the contract for project removal functionality that will be implemented in Stage 2.

This stage only modifies the interface — no implementation yet. The goal is to define a clean API before writing any logic.

## MCP Tools for This Stage

This stage works in **ctx-mcp-server** repository. Always use `project="ctx-mcp-server"` parameter:

```bash
# Read current interface
ctx:file-read path="src/Projects/ProjectServiceInterface.php" project="ctx-mcp-server"

# Check implementation for patterns
ctx:file-read path="src/Projects/ProjectService.php" project="ctx-mcp-server"

# Modify interface file
ctx:file-replace-content path="src/Projects/ProjectServiceInterface.php" project="ctx-mcp-server" ...

# Verify directory structure
ctx:directory-list path="src/Projects" project="ctx-mcp-server"
```

## Files

**Repository: ctx-mcp-server**

MODIFY:
- `src/Projects/ProjectServiceInterface.php` - Add 3 new method signatures

## Code References

- `src/Projects/ProjectServiceInterface.php:10-55` - Existing interface methods (pattern to follow)
- `src/Projects/ProjectService.php:85-100` - `switchToProject()` shows bool return pattern

## Implementation Details

### New Methods to Add

```php
/**
 * Remove a project by its absolute path
 * 
 * @param string $projectPath Absolute path to the project directory
 * @return bool True if project was found and removed, false if not found
 */
public function removeProject(string $projectPath): bool;

/**
 * Remove a project alias
 * 
 * @param string $alias The alias to remove
 * @return bool True if alias was found and removed, false if not found
 */
public function removeAlias(string $alias): bool;

/**
 * Check if a project exists by path or alias
 * 
 * @param string $pathOrAlias Absolute path or alias
 * @return bool True if project exists
 */
public function hasProject(string $pathOrAlias): bool;
```

### Design Decisions

1. **`removeProject()` returns bool** — Consistent with `switchToProject()` pattern
2. **`removeAlias()` is separate** — Allows fine-grained control, `removeProject()` can use it internally
3. **`hasProject()` accepts both** — Uses existing `resolvePathOrAlias()` internally for flexibility

## Definition of Done

- [ ] `removeProject(string $projectPath): bool` method added to interface
- [ ] `removeAlias(string $alias): bool` method added to interface
- [ ] `hasProject(string $pathOrAlias): bool` method added to interface
- [ ] All methods have proper PHPDoc comments
- [ ] Interface file passes PHP syntax check (`php -l`)
- [ ] No breaking changes to existing method signatures

## Dependencies

**Requires**: None (first stage)
**Enables**: Stage 2 (Implementation)
