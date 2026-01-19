# Stage 2: Implement ProjectService Methods

## Overview

Implement the three new methods defined in Stage 1 within `ProjectService`. This stage completes the service layer changes in `ctx-mcp-server`, enabling project removal functionality to be used by console commands.

Key consideration: When removing the current project, the service should clear `currentProject` but NOT auto-switch to another project — that decision belongs to the UI layer (console command).

## MCP Tools for This Stage

This stage works in **ctx-mcp-server** repository. Always use `project="ctx-mcp-server"` parameter:

```bash
# Read service implementation
ctx:file-read path="src/Projects/ProjectService.php" project="ctx-mcp-server"

# Read state DTO for understanding structure
ctx:file-read path="src/Projects/DTO/ProjectStateDTO.php" project="ctx-mcp-server"

# Modify service file
ctx:file-replace-content path="src/Projects/ProjectService.php" project="ctx-mcp-server" ...

# Search for usage patterns
ctx:file-search query="saveState" project="ctx-mcp-server"
```

## Files

**Repository: ctx-mcp-server**

MODIFY:
- `src/Projects/ProjectService.php` - Implement 3 new methods

## Code References

- `src/Projects/ProjectService.php:85-102` - `switchToProject()` pattern (check exists, modify state, save)
- `src/Projects/ProjectService.php:104-140` - `addProject()` pattern (alias handling, state update)
- `src/Projects/DTO/ProjectStateDTO.php:46-60` - `getAliasesForPath()` for finding related aliases

## Implementation Details

### `hasProject()` Implementation

```php
public function hasProject(string $pathOrAlias): bool
{
    $state = $this->getState();
    
    // Check if it's an alias first
    if (isset($state->aliases[$pathOrAlias])) {
        $pathOrAlias = $state->aliases[$pathOrAlias];
    }
    
    // Normalize path
    $projectPath = FSPath::create($pathOrAlias)->toString();
    
    return isset($state->projects[$projectPath]);
}
```

### `removeAlias()` Implementation

```php
public function removeAlias(string $alias): bool
{
    $state = $this->getState();
    
    if (!isset($state->aliases[$alias])) {
        return false;
    }
    
    unset($state->aliases[$alias]);
    $this->saveState($state);
    
    return true;
}
```

### `removeProject()` Implementation

```php
public function removeProject(string $projectPath): bool
{
    $state = $this->getState();
    
    // Normalize path
    $projectPath = FSPath::create($projectPath)->toString();
    
    // Check if project exists
    if (!isset($state->projects[$projectPath])) {
        return false;
    }
    
    // Remove from projects
    unset($state->projects[$projectPath]);
    
    // Remove all aliases pointing to this path
    foreach ($state->aliases as $alias => $path) {
        if ($path === $projectPath) {
            unset($state->aliases[$alias]);
        }
    }
    
    // Clear current project if it was the removed one
    if ($state->currentProject !== null && $state->currentProject->path === $projectPath) {
        $state->currentProject = null;
    }
    
    $this->saveState($state);
    
    return true;
}
```

### Key Behaviors

1. **Path normalization** — Always normalize via `FSPath::create()->toString()` for consistency
2. **Alias cleanup** — `removeProject()` automatically removes all associated aliases
3. **Current project handling** — If removing current project, set `currentProject = null`
4. **No auto-switch** — UI layer decides what happens after removal

### Edge Cases

- Removing non-existent project → return `false`, no state change
- Removing project with multiple aliases → all aliases removed
- Removing current project → `currentProject` becomes `null`
- Removing last project → valid, results in empty state

## Definition of Done

- [ ] `hasProject()` correctly resolves aliases and checks existence
- [ ] `removeAlias()` removes alias and persists state
- [ ] `removeProject()` removes project, aliases, and handles current project
- [ ] All methods use proper path normalization
- [ ] State is persisted after each modification
- [ ] Edge cases handled (non-existent, current project, etc.)
- [ ] Unit tests added for all three methods

## Dependencies

**Requires**: Stage 1 (interface definition)
**Enables**: Stage 3 (ProjectRemoveCommand)
