# Stage 6: Improve Remaining Commands UX

## Overview

Apply `ProjectRenderer` and improved styling to the remaining project commands: `ProjectCommand`, `ProjectAddCommand`, and `ProjectRemoveCommand`. This completes the UX overhaul for all project management commands.

## MCP Tools for This Stage

This stage works in **ctx** (generator) — the default project:

```bash
# Read commands to modify
ctx:file-read path="src/McpServer/Projects/Console/ProjectCommand.php"
ctx:file-read path="src/McpServer/Projects/Console/ProjectAddCommand.php"
ctx:file-read path="src/McpServer/Projects/Console/ProjectRemoveCommand.php"

# Read renderer for reference
ctx:file-read path="src/Console/Renderer/ProjectRenderer.php"

# Modify commands
ctx:file-replace-content path="src/McpServer/Projects/Console/ProjectCommand.php" ...
ctx:file-replace-content path="src/McpServer/Projects/Console/ProjectAddCommand.php" ...
```

## Files

**Repository: ctx (generator)**

MODIFY:
- `src/McpServer/Projects/Console/ProjectCommand.php` - Improve interactive selection
- `src/McpServer/Projects/Console/ProjectAddCommand.php` - Improve success output
- `src/McpServer/Projects/Console/ProjectRemoveCommand.php` - Apply renderer to confirmation/success

## Code References

- `src/McpServer/Projects/Console/ProjectCommand.php:55-120` - Current interactive selection
- `src/McpServer/Projects/Console/ProjectAddCommand.php:110-140` - Current success output
- `src/Console/Renderer/ProjectRenderer.php` - Renderer to use

## Implementation Details

### 1. ProjectCommand Improvements

#### Current Interactive Selection
```
Select a project to switch to:
  [0] /home/user/repos/ctx [ctx] (CURRENT)
  [1] /home/user/projects/myapp
  [2] Cancel - keep current project
 > 
```

#### Improved Selection
```
📁 Current: ctx (/home/user/repos/ctx)

Select project to switch to:

  ● ctx — /home/user/repos/ctx (current)
  ○ myapp — /home/user/projects/myapp
  ○ another — /home/user/projects/another
  ─────────────
  ✕ Cancel
```

#### Implementation Changes

```php
private function selectProjectInteractively(ProjectServiceInterface $projectService): int
{
    $projects = $projectService->getProjects();
    $currentProject = $projectService->getCurrentProject();

    if (empty($projects)) {
        $this->output->writeln('');
        $this->output->writeln(Style::info("No projects registered."));
        $this->output->writeln('');
        $this->output->writeln(Style::hint('ctx project:add <path>', 'Add your first project'));
        return Command::SUCCESS;
    }

    // Show current project header
    if ($currentProject !== null) {
        $aliases = $projectService->getAliasesForPath($currentProject->path);
        $aliasDisplay = !empty($aliases) ? $aliases[0] : \basename($currentProject->path);
        
        $this->output->writeln('');
        $this->output->writeln(\sprintf(
            '📁 Current: %s (%s)',
            Style::highlight($aliasDisplay),
            Style::path($currentProject->path),
        ));
    }

    $this->output->writeln('');
    $this->output->writeln(Style::bold('Select project to switch to:'));
    $this->output->writeln('');

    // Build formatted choices
    $choices = [];
    $choiceMap = [];

    foreach ($projects as $path => $info) {
        $aliases = $projectService->getAliasesForPath($path);
        $aliasDisplay = !empty($aliases) ? $aliases[0] : \basename($path);
        $isCurrent = $currentProject && $currentProject->path === $path;
        
        $dot = Style::statusDot($isCurrent);
        $currentLabel = $isCurrent ? ' <fg=yellow>(current)</>' : '';
        
        $displayString = \sprintf(
            '%s %s — %s%s',
            $dot,
            Style::value($aliasDisplay),
            Style::muted($path),
            $currentLabel,
        );
        
        $choices[] = $displayString;
        $choiceMap[$displayString] = $path;
    }

    // Separator and cancel
    $separator = Style::muted('─────────────');
    $cancelOption = Style::muted('✕ Cancel');
    $choices[] = $separator;
    $choices[] = $cancelOption;

    // Create question
    $helper = $this->getHelper('question');
    $question = new ChoiceQuestion('', $choices, 0);
    $question->setErrorMessage('Invalid selection.');

    $selectedChoice = $helper->ask($this->input, $this->output, $question);

    // Handle separator (re-prompt) and cancel
    if ($selectedChoice === $separator) {
        return $this->selectProjectInteractively($projectService);
    }
    
    if ($selectedChoice === $cancelOption) {
        $this->output->writeln('');
        $this->output->writeln(Style::info('Operation cancelled.'));
        return Command::SUCCESS;
    }

    // Switch to selected
    $selectedPath = $choiceMap[$selectedChoice];
    
    if ($projectService->switchToProject($selectedPath)) {
        $this->output->writeln('');
        $this->output->writeln(Style::success(\sprintf('✓ Switched to: %s', $selectedPath)));
        return Command::SUCCESS;
    }

    $this->output->writeln('');
    $this->output->writeln(Style::error('Failed to switch project.'));
    return Command::FAILURE;
}
```

### 2. ProjectAddCommand Improvements

#### Current Output
```
[OK] Added project: /home/user/projects/myapp
Project alias 'myapp' has been set
```

#### Improved Output
```
✓ Project added successfully!

  ╭──────────────────────────────────────────╮
  │  Alias:     myapp                        │
  │  Path:      /home/user/projects/myapp    │
  │  Config:    context.yaml                 │
  │  Env:       .env.local                   │
  ╰──────────────────────────────────────────╯

💡
   ctx project myapp      Switch to this project
```

#### Implementation Changes

Replace the success output section:

```php
// After successful add
$renderer = new ProjectRenderer($this->output, $projectService);

$renderer->renderSuccessBox('Project added successfully!', [
    'Alias' => $this->name,
    'Path' => $projectPath,
    'Config' => $this->configFile,
    'Env' => $this->envFile,
]);

$switchCommand = $this->name ? "ctx project {$this->name}" : "ctx project \"{$projectPath}\"";
$renderer->renderHints([
    $switchCommand => 'Switch to this project',
]);
```

### 3. ProjectRemoveCommand Improvements

Apply renderer to confirmation and success output:

#### Confirmation Block
```php
$renderer = new ProjectRenderer($this->output, $projectService);

$renderer->renderConfirmationBlock('Remove this project?', [
    'Path' => $projectPath,
    'Aliases' => implode(', ', $aliases),
    'Status' => $isCurrent ? 'Current project' : 'Not current',
]);

if (!empty($aliases) && !$this->keepAliases) {
    $this->output->writeln(Style::muted(\sprintf(
        '  This will also remove %d alias%s.',
        count($aliases),
        count($aliases) === 1 ? '' : 'es',
    )));
    $this->output->writeln('');
}
```

#### Success Message
```php
$this->output->writeln('');
$this->output->writeln(Style::success(\sprintf('✓ Project "%s" removed.', $aliasDisplay)));
$this->output->writeln('');

$remainingCount = count($projectService->getProjects());
if ($remainingCount > 0) {
    $renderer->renderHints([
        'ctx projects' => \sprintf('%d project%s remaining', $remainingCount, $remainingCount === 1 ? '' : 's'),
    ]);
}
```

### Consistency Checklist

Ensure all commands follow these patterns:

| Element | Pattern |
|---------|---------|
| Success icon | `✓` (green) |
| Warning icon | `⚠️` |
| Info icon | `💡` |
| Current indicator | `● ← current` |
| Other indicator | `○` |
| Separator | `─────────────` |
| Cancel option | `✕ Cancel` |
| Dates | `Jan 15, 2025` |
| Paths | `<fg=bright-blue>` |
| Values | `<fg=bright-white>` |

## Definition of Done

- [ ] `ProjectCommand` interactive selection has improved visuals
- [ ] `ProjectCommand` shows current project header
- [ ] `ProjectCommand` uses status dots (●/○)
- [ ] `ProjectAddCommand` shows success box with details
- [ ] `ProjectAddCommand` shows helpful hint for switching
- [ ] `ProjectRemoveCommand` uses `renderConfirmationBlock()`
- [ ] `ProjectRemoveCommand` shows alias count in confirmation
- [ ] `ProjectRemoveCommand` shows success message with remaining count
- [ ] All commands use consistent icons and colors
- [ ] All commands use human-readable dates
- [ ] Manual testing of all commands completed
- [ ] No regressions in functionality

## Dependencies

**Requires**: Stage 4 (ProjectRenderer), Stage 5 (pattern established)
**Enables**: Feature complete
