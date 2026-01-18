# Stage 3: Create ProjectRemoveCommand

## Overview

Create the `project:remove` console command in the main CTX repository. This command allows users to remove projects by alias, path, `.` (current directory), or through interactive selection.

This stage focuses on functionality — visual styling improvements come in Stage 6.

## MCP Tools for This Stage

This stage works in **ctx** (generator) — the default project. No `project` parameter needed:

```bash
# Read existing commands for patterns
ctx:file-read path="src/McpServer/Projects/Console/ProjectAddCommand.php"
ctx:file-read path="src/McpServer/Projects/Console/ProjectCommand.php"

# List console directory
ctx:directory-list path="src/McpServer/Projects/Console"

# Create new command file
ctx:file-write path="src/McpServer/Projects/Console/ProjectRemoveCommand.php" ...

# Search for patterns
ctx:file-search query="ConfirmationQuestion"
```

## Files

**Repository: ctx (generator)**

CREATE:
- `src/McpServer/Projects/Console/ProjectRemoveCommand.php` - New command

## Code References

- `src/McpServer/Projects/Console/ProjectAddCommand.php:60-90` - Path normalization pattern
- `src/McpServer/Projects/Console/ProjectCommand.php:55-120` - Interactive selection pattern
- `src/McpServer/Projects/Console/ProjectAddCommand.php:27-60` - Argument/Option attributes

## Implementation Details

### Command Signature

```bash
# By alias
ctx project:remove myproject

# By absolute path  
ctx project:remove /home/user/projects/myapp

# Current directory
ctx project:remove .

# Interactive (no argument)
ctx project:remove

# Skip confirmation
ctx project:remove myproject --force

# Keep aliases (don't auto-remove)
ctx project:remove myproject --keep-aliases
```

### Class Structure

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Projects\Console;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\Console\BaseCommand;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Projects\ProjectServiceInterface;
use Spiral\Console\Attribute\Argument;
use Spiral\Console\Attribute\Option;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(
    name: 'project:remove',
    description: 'Remove a registered project',
)]
final class ProjectRemoveCommand extends BaseCommand
{
    #[Argument(
        name: 'path',
        description: 'Path or alias of the project to remove. Use "." for current directory.',
    )]
    protected ?string $path = null;

    #[Option(
        name: 'force',
        shortcut: 'f',
        description: 'Skip confirmation prompt',
    )]
    protected bool $force = false;

    #[Option(
        name: 'keep-aliases',
        description: 'Keep aliases when removing project (they become orphaned)',
    )]
    protected bool $keepAliases = false;

    public function __invoke(
        DirectoriesInterface $dirs,
        ProjectServiceInterface $projectService,
    ): int {
        // Implementation...
    }
}
```

### Core Logic Flow

```
1. If no path argument:
   → Show interactive project selection
   → User selects project to remove
   
2. Resolve path:
   → If "." → FSPath::cwd()
   → If alias → projectService->resolvePathOrAlias()
   → Normalize to absolute path

3. Validate project exists:
   → If not found → error message, return FAILURE

4. Show project info and confirm:
   → Display path, aliases, current status
   → If --force → skip confirmation
   → If user declines → return SUCCESS (cancelled)

5. Handle current project:
   → If removing current → prompt for new selection
   → Or clear current (user can switch manually)

6. Remove project:
   → If --keep-aliases → remove aliases first manually, then project
   → Else → projectService->removeProject() (auto-removes aliases)

7. Success message
```

### Interactive Selection (no argument)

```php
private function selectProjectInteractively(ProjectServiceInterface $projectService): ?string
{
    $projects = $projectService->getProjects();
    
    if (empty($projects)) {
        $this->output->info("No projects registered.");
        return null;
    }
    
    $choices = [];
    $choiceMap = [];
    
    foreach ($projects as $path => $info) {
        $aliases = $projectService->getAliasesForPath($path);
        $aliasStr = !empty($aliases) ? ' [' . implode(', ', $aliases) . ']' : '';
        $display = $path . $aliasStr;
        
        $choices[] = $display;
        $choiceMap[$display] = $path;
    }
    
    $choices[] = 'Cancel';
    
    $helper = $this->getHelper('question');
    $question = new ChoiceQuestion('Select project to remove:', $choices);
    
    $selected = $helper->ask($this->input, $this->output, $question);
    
    if ($selected === 'Cancel') {
        return null;
    }
    
    return $choiceMap[$selected];
}
```

### Confirmation Prompt

```php
private function confirmRemoval(
    string $projectPath,
    array $aliases,
    bool $isCurrent,
    ProjectServiceInterface $projectService,
): bool {
    if ($this->force) {
        return true;
    }
    
    $this->output->warning("You are about to remove the following project:");
    $this->output->newLine();
    $this->output->text("  Path: " . $projectPath);
    
    if (!empty($aliases)) {
        $this->output->text("  Aliases: " . implode(', ', $aliases));
    }
    
    if ($isCurrent) {
        $this->output->text("  Status: Current project");
    }
    
    $this->output->newLine();
    
    $helper = $this->getHelper('question');
    $question = new ConfirmationQuestion('Are you sure? [y/N] ', false);
    
    return $helper->ask($this->input, $this->output, $question);
}
```

### Handle Current Project Removal

```php
private function handleCurrentProjectRemoval(ProjectServiceInterface $projectService): void
{
    $projects = $projectService->getProjects();
    
    if (empty($projects)) {
        $this->output->info("No other projects available. Current project cleared.");
        return;
    }
    
    // Ask user to select new current project
    $this->output->newLine();
    $this->output->text("The removed project was your current project.");
    
    $choices = [];
    $choiceMap = [];
    
    foreach ($projects as $path => $info) {
        $aliases = $projectService->getAliasesForPath($path);
        $aliasStr = !empty($aliases) ? ' [' . implode(', ', $aliases) . ']' : '';
        $display = $path . $aliasStr;
        
        $choices[] = $display;
        $choiceMap[$display] = $path;
    }
    
    $choices[] = 'None - clear current project';
    
    $helper = $this->getHelper('question');
    $question = new ChoiceQuestion('Select new current project:', $choices, 0);
    
    $selected = $helper->ask($this->input, $this->output, $question);
    
    if ($selected !== 'None - clear current project') {
        $newPath = $choiceMap[$selected];
        $projectService->switchToProject($newPath);
        $this->output->success("Switched to: " . $newPath);
    }
}
```

## Definition of Done

- [ ] Command registered and accessible via `ctx project:remove`
- [ ] Works with alias: `ctx project:remove myalias`
- [ ] Works with path: `ctx project:remove /path/to/project`
- [ ] Works with `.`: `ctx project:remove .`
- [ ] Works interactively: `ctx project:remove` (no args)
- [ ] `--force` skips confirmation
- [ ] `--keep-aliases` preserves aliases
- [ ] Removing current project prompts for new selection
- [ ] Proper error messages for non-existent projects
- [ ] Success message after removal

## Dependencies

**Requires**: Stage 2 (ProjectService implementation)
**Enables**: Stage 6 (UX improvements for this command)
