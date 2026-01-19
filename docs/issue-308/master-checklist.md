# Feature: Project Remove Command & Project Management UX Improvements

## Overview

Add the ability to remove projects from CTX via `project:remove` command and modernize the visual design of all project management console commands (`project`, `project:list`, `project:add`, `project:remove`).

Currently CTX supports adding, listing, and switching projects but lacks removal capability. Users must manually edit `.project-state.json` to remove unwanted projects. Additionally, the current UX is basic and utilitarian — plain tables, raw timestamps, no visual hierarchy.

### Goals
1. Add `project:remove` command with full functionality (by alias, path, `.`, or interactive)
2. Create reusable `ProjectRenderer` for consistent project display
3. Modernize all project commands with improved visual output
4. Maintain backward compatibility

## Stage Dependencies

```
Stage 1 (Interface) → Stage 2 (Implementation) → Stage 3 (Remove Command)
                                                         ↓
                    Stage 4 (ProjectRenderer) → Stage 5 (UX: list) → Stage 6 (UX: others)
```

---

## Usage Instructions

⚠️ **Keep this checklist updated:**
- Mark completed substeps immediately with [x]
- Add notes about deviations or challenges
- Document decisions differing from plan
- Update status when starting/completing stages

⚠️ **Two repositories involved:**
- `ctx-mcp-server` - Stages 1-2 (interface & implementation)
- `ctx` (generator) - Stages 3-6 (commands & UX)


## Development Progress

### Stage 1: Extend ProjectServiceInterface
- [x] 1.1: Add `removeProject(string $projectPath): bool` method to interface
- [x] 1.2: Add `removeAlias(string $alias): bool` method to interface
- [x] 1.3: Add `hasProject(string $pathOrAlias): bool` method to interface
- [x] 1.4: Verify interface is syntactically correct

**Notes**: Added three methods with PHPDoc documentation explaining expected behavior.
**Status**: ✅ Complete
**Completed**: 2025-01-18

---

### Stage 2: Implement ProjectService Methods
- [x] 2.1: Implement `hasProject()` method with alias resolution
- [x] 2.2: Implement `removeAlias()` method with state persistence
- [x] 2.3: Implement `removeProject()` method with alias cleanup
- [x] 2.4: Handle current project cleanup when removing active project
- [x] 2.5: Add unit tests for new methods

**Notes**: Implemented all three methods. Added 8 unit tests covering: hasProject by path/alias, removeAlias success/failure, removeProject success/failure, alias cleanup on project removal, current project cleanup.
**Status**: ✅ Complete
**Completed**: 2025-01-18

---

### Stage 3: Create ProjectRemoveCommand
- [x] 3.1: Create basic command structure with argument and options
- [x] 3.2: Implement path normalization (support `.`, alias, absolute path)
- [x] 3.3: Add confirmation prompt (skip with `--force`)
- [x] 3.4: Implement interactive project selection (no arguments)
- [x] 3.5: Handle current project removal with new project selection
- [x] 3.6: Add `--with-aliases` / `--keep-aliases` flags
- [x] 3.7: Test all removal scenarios

**Notes**: Created `ProjectRemoveCommand` with full functionality. Supports path/alias/`.` input, interactive selection, `--force` flag, and `--keep-aliases` flag (with warning that it's not meaningful). Shows helpful tip after removing current project.
**Status**: ✅ Complete
**Completed**: 2025-01-18

---

### Stage 4: Create ProjectRenderer
- [x] 4.1: Create `ProjectRenderer` class with constructor dependencies
- [x] 4.2: Implement `renderProjectCard()` method
- [x] 4.3: Implement `renderProjectList()` method
- [x] 4.4: Implement `renderBox()` and `renderHints()` helper methods
- [x] 4.5: Add `formatDate()` for human-readable dates (Jan 15, 2025)
- [x] 4.6: Add box-drawing utilities to `Style` class

**Notes**: Created `ProjectRenderer` with box-drawing card layout. Added `statusIndicator()`, `boxLine()`, and `horizontalRule()` to `Style` class. Cards show status indicator (● current / ○ other), path, aliases, config/env files, and formatted date.
**Status**: ✅ Complete
**Completed**: 2025-01-18

---

### Stage 5: Improve ProjectListCommand UX
- [x] 5.1: Replace table output with card-based layout
- [x] 5.2: Add status indicators (● current, ○ other)
- [x] 5.3: Format dates in human-readable format
- [x] 5.4: Add helpful command hints at bottom
- [x] 5.5: Test output with various project configurations

**Notes**: Replaced table with `ProjectRenderer` card layout. Added command hints for `project`, `project:add`, and `project:remove`. Code reduced from 70 to 50 lines.
**Status**: ✅ Complete
**Completed**: 2025-01-18

---

### Stage 6: Improve Remaining Commands UX
- [x] 6.1: Improve `ProjectCommand` interactive selection
- [x] 6.2: Improve `ProjectAddCommand` success output
- [x] 6.3: Apply `ProjectRenderer` to `ProjectRemoveCommand`
- [x] 6.4: Ensure consistent styling across all commands
- [x] 6.5: Manual testing of all project commands

**Notes**: All commands now use `ProjectRenderer` and `Style` utilities. Interactive selection uses ● / ○ indicators. Success/info messages use ✓ / → / ! symbols. Consistent spacing and formatting across all commands.
**Status**: ✅ Complete
**Completed**: 2025-01-18

---

## Codebase References

### Existing Commands (patterns to follow)
- `src/McpServer/Projects/Console/ProjectAddCommand.php` - Command structure, path normalization
- `src/McpServer/Projects/Console/ProjectListCommand.php` - Current table output (to replace)
- `src/McpServer/Projects/Console/ProjectCommand.php` - Interactive selection pattern

### Service Layer (ctx-mcp-server)
- `src/Projects/ProjectServiceInterface.php` - Interface to extend
- `src/Projects/ProjectService.php` - Implementation to modify
- `src/Projects/DTO/ProjectStateDTO.php` - State structure
- `src/Projects/Repository/ProjectStateRepository.php` - Persistence

### Styling
- `src/Console/Renderer/Style.php` - Existing style utilities
- `src/Console/BaseCommand.php` - Base command class

## MCP Tools & Multi-Project Navigation

This feature spans **two repositories**. Use MCP tools to navigate between them:

### Available Projects

Use `ctx:projects-list` tool to see registered projects:

| Project | Alias | Description |
|---------|-------|-------------|
| CTX Generator | `ctx` | Main project - console commands, renderers |
| ctx-mcp-server | `ctx-mcp-server` | Library - ProjectService, interfaces, DTOs |

### Tools with Project Parameter

Most MCP tools accept a `project` parameter to work across repositories:

```
# Read file from ctx-mcp-server
ctx:file-read path="src/Projects/ProjectServiceInterface.php" project="ctx-mcp-server"

# Search in ctx-mcp-server
ctx:file-search query="removeProject" project="ctx-mcp-server"

# List directory in ctx-mcp-server
ctx:directory-list path="src/Projects" project="ctx-mcp-server"
```

### Project Contents Map

#### ctx-mcp-server (Stages 1-2)
```
src/Projects/
├── ProjectServiceInterface.php  ← MODIFY: Add new methods
├── ProjectService.php           ← MODIFY: Implement methods
├── DTO/
│   ├── CurrentProjectDTO.php
│   ├── ProjectDTO.php
│   └── ProjectStateDTO.php      ← Reference: state structure
└── Repository/
    ├── ProjectStateRepository.php
    └── ProjectStateRepositoryInterface.php
```

#### ctx / generator (Stages 3-6)
```
src/McpServer/Projects/Console/
├── ProjectAddCommand.php        ← MODIFY: UX improvements
├── ProjectCommand.php           ← MODIFY: UX improvements
├── ProjectListCommand.php       ← MODIFY: UX improvements
└── ProjectRemoveCommand.php     ← CREATE: New command

src/Console/Renderer/
├── Style.php                    ← MODIFY: Add box utilities
└── ProjectRenderer.php          ← CREATE: New renderer
```

### Quick Reference Commands

```bash
# Get list of projects
ctx:projects-list

# Read interface (ctx-mcp-server)
ctx:file-read path="src/Projects/ProjectServiceInterface.php" project="ctx-mcp-server"

# Read service implementation (ctx-mcp-server)
ctx:file-read path="src/Projects/ProjectService.php" project="ctx-mcp-server"

# Read existing command (ctx - default project)
ctx:file-read path="src/McpServer/Projects/Console/ProjectAddCommand.php"

# Search for pattern across project
ctx:file-search query="ProjectServiceInterface" project="ctx-mcp-server"

# View Style class
ctx:file-read path="src/Console/Renderer/Style.php"
```
