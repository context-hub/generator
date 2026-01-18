# Stage 5: Improve ProjectListCommand UX

## Overview

Replace the current table-based output in `ProjectListCommand` with the new card-based layout using `ProjectRenderer`. This is the most visible UX change and serves as the template for other command improvements.

## MCP Tools for This Stage

This stage works in **ctx** (generator) — the default project:

```bash
# Read current implementation
ctx:file-read path="src/McpServer/Projects/Console/ProjectListCommand.php"

# Read renderer created in Stage 4
ctx:file-read path="src/Console/Renderer/ProjectRenderer.php"

# Modify command
ctx:file-replace-content path="src/McpServer/Projects/Console/ProjectListCommand.php" ...
```

## Files

**Repository: ctx (generator)**

MODIFY:
- `src/McpServer/Projects/Console/ProjectListCommand.php` - Replace table with renderer

## Code References

- `src/McpServer/Projects/Console/ProjectListCommand.php` - Current implementation
- `src/Console/Renderer/ProjectRenderer.php` - New renderer (from Stage 4)

## Implementation Details

### Current Implementation (to replace)

```php
public function __invoke(ProjectServiceInterface $projectService): int
{
    $projects = $projectService->getProjects();
    // ...
    
    // Create and configure table
    $table = new Table($this->output);
    $table->setHeaders(['Path', 'Config File', 'Env File', 'Aliases', 'Added', 'Current']);
    
    foreach ($projects as $path => $info) {
        $table->addRow([...]);
    }
    
    $table->render();
    return Command::SUCCESS;
}
```

### New Implementation

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Projects\Console;

use Butschster\ContextGenerator\Console\BaseCommand;
use Butschster\ContextGenerator\Console\Renderer\ProjectRenderer;
use Butschster\ContextGenerator\McpServer\Projects\ProjectServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(
    name: 'project:list',
    description: 'List all registered projects',
    aliases: ['projects'],
)]
final class ProjectListCommand extends BaseCommand
{
    public function __invoke(ProjectServiceInterface $projectService): int
    {
        $renderer = new ProjectRenderer($this->output, $projectService);
        
        $projects = $projectService->getProjects();
        
        if (empty($projects)) {
            $renderer->renderEmpty();
            return Command::SUCCESS;
        }
        
        $currentPath = $projectService->getCurrentProject()?->path;
        $renderer->renderProjectList($projects, $currentPath);
        
        return Command::SUCCESS;
    }
}
```

### Expected Output

**Before:**
```
+----------------------------------+-------------+----------+---------+---------------------+---------+
| Path                             | Config File | Env File | Aliases | Added               | Current |
+----------------------------------+-------------+----------+---------+---------------------+---------+
| /home/user/repos/ctx             |             |          | ctx     | 2025-01-15 10:30:00 | ✓       |
| /home/user/projects/myapp        |             |          |         | 2025-01-10 14:20:00 |         |
+----------------------------------+-------------+----------+---------+---------------------+---------+
```

**After:**
```
╭────────────────────── 📁 Projects (2 registered) ──────────────────────╮
╰────────────────────────────────────────────────────────────────────────╯

 ● ctx                                              ← current
   /home/user/repos/context-hub/generator
   Config: context.yaml · Added: Jan 15, 2025

 ○ myapp
   /home/user/projects/myapp
   Added: Jan 10, 2025

──────────────────────────────────────────────────────────────────────────
💡
   ctx project <n>        Switch project
   ctx project:add <path>  Add project
   ctx project:remove      Remove project
```

### Removed Dependencies

The following imports can be removed:
- `use Symfony\Component\Console\Helper\Table;`
- `use Butschster\ContextGenerator\Console\Renderer\Style;` (renderer uses it internally)

### Edge Cases to Handle

1. **No projects** — Uses `renderEmpty()` with helpful hint
2. **No current project** — All projects show ○, no "← current" indicator
3. **Long paths** — Should wrap or truncate gracefully
4. **Many projects** — List should remain readable (consider pagination for 20+ projects in future)

## Definition of Done

- [ ] Table-based output removed
- [ ] `ProjectRenderer` used for all output
- [ ] Empty state shows helpful message and hints
- [ ] Current project clearly indicated with ● and "← current"
- [ ] Other projects show ○
- [ ] Dates formatted as "Jan 15, 2025"
- [ ] Command hints displayed at bottom
- [ ] Aliases shown in project cards
- [ ] Config file shown when set
- [ ] Visual output matches expected design
- [ ] No regressions in functionality

## Dependencies

**Requires**: Stage 4 (ProjectRenderer exists)
**Enables**: Stage 6 (pattern established for other commands)
