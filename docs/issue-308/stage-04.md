# Stage 4: Create ProjectRenderer

## Overview

Create a centralized `ProjectRenderer` class that handles all project-related console output formatting. This ensures consistent visual styling across all project commands and makes future UX changes easier.

This stage builds the foundation for Stages 5-6 which will apply the renderer to existing commands.

## MCP Tools for This Stage

This stage works in **ctx** (generator) — the default project:

```bash
# Read existing Style class
ctx:file-read path="src/Console/Renderer/Style.php"

# Check existing renderer patterns
ctx:file-read path="src/Console/Renderer/GenerateCommandRenderer.php"

# List Renderer directory
ctx:directory-list path="src/Console/Renderer"

# Create new renderer
ctx:file-write path="src/Console/Renderer/ProjectRenderer.php" ...

# Modify Style class
ctx:file-replace-content path="src/Console/Renderer/Style.php" ...
```

## Files

**Repository: ctx (generator)**

CREATE:
- `src/Console/Renderer/ProjectRenderer.php` - Centralized project rendering

MODIFY:
- `src/Console/Renderer/Style.php` - Add box-drawing and date formatting utilities

## Code References

- `src/Console/Renderer/Style.php` - Existing style patterns to extend
- `src/Console/Renderer/GenerateCommandRenderer.php` - Example of command-specific renderer
- `src/McpServer/Projects/Console/ProjectListCommand.php:35-70` - Current rendering (to replace)

## Implementation Details

### Style.php Additions

```php
/**
 * Create a box with title and content lines
 */
public static function box(string $title, array $lines, int $width = 60): string
{
    $result = [];
    
    // Top border with title
    $titleLen = \mb_strlen(\strip_tags($title));
    $padding = ($width - $titleLen - 4) / 2;
    $leftPad = \str_repeat('─', (int) \floor($padding));
    $rightPad = \str_repeat('─', (int) \ceil($padding));
    
    $result[] = \sprintf('<fg=blue>╭%s</> %s <fg=blue>%s╮</>', $leftPad, $title, $rightPad);
    
    // Content lines
    foreach ($lines as $line) {
        $lineLen = \mb_strlen(\strip_tags($line));
        $rightSpace = $width - $lineLen - 4;
        $result[] = \sprintf('<fg=blue>│</>  %s%s  <fg=blue>│</>', $line, \str_repeat(' ', \max(0, $rightSpace)));
    }
    
    // Bottom border
    $result[] = \sprintf('<fg=blue>╰%s╯</>', \str_repeat('─', $width - 2));
    
    return \implode("\n", $result);
}

/**
 * Format datetime to human-readable format
 */
public static function humanDate(string $datetime): string
{
    $date = new \DateTimeImmutable($datetime);
    return $date->format('M j, Y');
}

/**
 * Status indicator (filled or empty circle)
 */
public static function statusDot(bool $active): string
{
    return $active 
        ? '<fg=bright-green>●</>' 
        : '<fg=gray>○</>';
}

/**
 * Render hint line
 */
public static function hint(string $command, string $description): string
{
    return \sprintf('   <fg=cyan>%s</>  %s', \str_pad($command, 20), self::muted($description));
}
```

### ProjectRenderer Class

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console\Renderer;

use Butschster\ContextGenerator\McpServer\Projects\DTO\ProjectDTO;
use Butschster\ContextGenerator\McpServer\Projects\ProjectServiceInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ProjectRenderer
{
    public function __construct(
        private readonly OutputInterface $output,
        private readonly ProjectServiceInterface $projectService,
    ) {}

    /**
     * Render a single project as a card
     */
    public function renderProjectCard(
        string $path,
        ProjectDTO $project,
        bool $isCurrent,
    ): void {
        $aliases = $this->projectService->getAliasesForPath($path);
        $aliasDisplay = !empty($aliases) ? $aliases[0] : \basename($path);
        
        // Header line: status dot + alias/name + current indicator
        $currentIndicator = $isCurrent ? ' <fg=yellow>← current</>' : '';
        $this->output->writeln(\sprintf(
            ' %s <fg=bright-white;options=bold>%s</>%s',
            Style::statusDot($isCurrent),
            $aliasDisplay,
            $currentIndicator,
        ));
        
        // Path
        $this->output->writeln(\sprintf('   %s', Style::path($path)));
        
        // Config file (if set)
        if ($project->configFile !== null) {
            $this->output->writeln(\sprintf(
                '   Config: %s · Added: %s',
                Style::file($project->configFile),
                Style::humanDate($project->addedAt),
            ));
        } else {
            $this->output->writeln(\sprintf(
                '   Added: %s',
                Style::humanDate($project->addedAt),
            ));
        }
        
        $this->output->writeln('');
    }

    /**
     * Render the full project list
     */
    public function renderProjectList(array $projects, ?string $currentPath): void
    {
        $count = \count($projects);
        
        // Header box
        $this->output->writeln(Style::box(
            \sprintf('📁 Projects (%d registered)', $count),
            [],
            66,
        ));
        $this->output->writeln('');
        
        // Render each project
        foreach ($projects as $path => $project) {
            $this->renderProjectCard($path, $project, $path === $currentPath);
        }
        
        // Separator and hints
        $this->output->writeln(Style::separator('─', 66));
        $this->renderHints([
            'ctx project <name>' => 'Switch project',
            'ctx project:add <path>' => 'Add project',
            'ctx project:remove' => 'Remove project',
        ]);
    }

    /**
     * Render success box after adding/removing project
     */
    public function renderSuccessBox(string $title, array $details): void
    {
        $lines = [];
        foreach ($details as $key => $value) {
            if ($value !== null && $value !== '') {
                $lines[] = \sprintf('%s: %s', Style::label(\str_pad($key, 8)), Style::value($value));
            }
        }
        
        $this->output->writeln('');
        $this->output->writeln(Style::success('✓ ' . $title));
        $this->output->writeln('');
        $this->output->writeln(Style::box('', $lines, 50));
    }

    /**
     * Render confirmation block for destructive action
     */
    public function renderConfirmationBlock(string $action, array $details): void
    {
        $this->output->writeln('');
        $this->output->writeln(Style::warning('⚠️  ' . $action));
        $this->output->writeln('');
        
        $lines = [];
        foreach ($details as $key => $value) {
            if ($value !== null && $value !== '') {
                $lines[] = \sprintf('%s: %s', Style::label(\str_pad($key, 8)), Style::value($value));
            }
        }
        
        $this->output->writeln(Style::box('', $lines, 50));
        $this->output->writeln('');
    }

    /**
     * Render command hints at bottom
     */
    public function renderHints(array $hints): void
    {
        $this->output->writeln(Style::muted('💡'));
        foreach ($hints as $command => $description) {
            $this->output->writeln(Style::hint($command, $description));
        }
    }

    /**
     * Render "no projects" message
     */
    public function renderEmpty(): void
    {
        $this->output->writeln('');
        $this->output->writeln(Style::info('No projects registered.'));
        $this->output->writeln('');
        $this->renderHints([
            'ctx project:add <path>' => 'Add your first project',
        ]);
    }
}
```

### Integration Pattern

Commands will use the renderer like this:

```php
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
```

## Definition of Done

- [ ] `Style::box()` creates bordered box with title
- [ ] `Style::humanDate()` formats dates as "Jan 15, 2025"
- [ ] `Style::statusDot()` returns ● or ○ with colors
- [ ] `Style::hint()` formats command hints consistently
- [ ] `ProjectRenderer` class created with all methods
- [ ] `renderProjectCard()` displays single project info
- [ ] `renderProjectList()` displays all projects with header
- [ ] `renderSuccessBox()` for success messages
- [ ] `renderConfirmationBlock()` for destructive actions
- [ ] `renderHints()` shows helpful commands
- [ ] `renderEmpty()` handles no-projects case
- [ ] All methods produce proper ANSI-colored output

## Dependencies

**Requires**: Stage 3 (command exists to use renderer)
**Enables**: Stage 5 (apply to ProjectListCommand), Stage 6 (apply to other commands)
