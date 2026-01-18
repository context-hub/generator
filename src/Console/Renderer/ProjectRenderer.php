<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console\Renderer;

use Butschster\ContextGenerator\McpServer\Projects\DTO\ProjectDTO;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Renders project information in console output with consistent styling
 */
final readonly class ProjectRenderer
{
    // Box drawing characters
    private const string BOX_TOP_LEFT = '╭';
    private const string BOX_TOP_RIGHT = '╮';
    private const string BOX_BOTTOM_LEFT = '╰';
    private const string BOX_BOTTOM_RIGHT = '╯';
    private const string BOX_HORIZONTAL = '─';
    private const string BOX_VERTICAL = '│';

    // Status indicators
    private const string STATUS_CURRENT = '●';
    private const string STATUS_OTHER = '○';

    public function __construct(
        private OutputInterface $output,
        private int $boxWidth = 70,
    ) {}

    /**
     * Render a single project card
     *
     * @param string[] $aliases
     */
    public function renderProjectCard(
        string $path,
        ProjectDTO $project,
        array $aliases = [],
        bool $isCurrent = false,
    ): void {
        $statusIndicator = $isCurrent ? self::STATUS_CURRENT : self::STATUS_OTHER;
        $statusColor = $isCurrent ? 'bright-green' : 'gray';
        $statusLabel = $isCurrent ? 'CURRENT' : '';

        // Top border with status
        $this->renderBoxTop($statusIndicator, $statusColor, $statusLabel);

        // Project path (main content)
        $this->renderBoxLine(Style::path($path));

        // Aliases
        if (!empty($aliases)) {
            $this->renderBoxLine(
                Style::muted('Aliases: ') . Style::label(\implode(', ', $aliases)),
            );
        }

        // Config file
        if ($project->configFile !== null) {
            $this->renderBoxLine(
                Style::muted('Config: ') . Style::file($project->configFile),
            );
        }

        // Env file
        if ($project->envFile !== null) {
            $this->renderBoxLine(
                Style::muted('Env: ') . Style::file($project->envFile),
            );
        }

        // Added date
        $this->renderBoxLine(
            Style::muted('Added: ') . Style::value($this->formatDate($project->addedAt)),
        );

        // Bottom border
        $this->renderBoxBottom();
    }

    /**
     * Render a list of projects
     *
     * @param array<string, ProjectDTO> $projects
     * @param array<string, string> $aliases Alias to path mapping
     */
    public function renderProjectList(
        array $projects,
        array $aliases,
        ?string $currentProjectPath = null,
    ): void {
        if (empty($projects)) {
            $this->output->writeln(Style::muted('No projects registered.'));
            return;
        }

        // Build path to aliases map
        $pathToAliases = $this->buildPathToAliasesMap($aliases);

        // Render each project
        foreach ($projects as $path => $project) {
            $projectAliases = $pathToAliases[$path] ?? [];
            $isCurrent = $currentProjectPath === $path;

            $this->renderProjectCard($path, $project, $projectAliases, $isCurrent);
            $this->output->writeln('');
        }
    }

    /**
     * Render helpful command hints
     *
     * @param array<string, string> $hints Command => description mapping
     */
    public function renderHints(array $hints): void
    {
        $this->output->writeln(Style::muted('Commands:'));

        // Calculate max command length for alignment
        $maxLength = 0;
        foreach (\array_keys($hints) as $command) {
            $maxLength = \max($maxLength, \mb_strlen($command));
        }

        foreach ($hints as $command => $description) {
            $padding = \str_repeat(' ', $maxLength - \mb_strlen($command));
            $this->output->writeln(\sprintf(
                '  %s%s  %s',
                Style::command($command),
                $padding,
                Style::muted($description),
            ));
        }
    }

    /**
     * Render a simple info box with a message
     */
    public function renderBox(string $message, string $type = 'info'): void
    {
        $color = match ($type) {
            'success' => 'green',
            'warning' => 'yellow',
            'error' => 'red',
            default => 'blue',
        };

        $this->output->writeln(\sprintf('<fg=%s>%s</>', $color, self::BOX_TOP_LEFT . \str_repeat(self::BOX_HORIZONTAL, $this->boxWidth - 2) . self::BOX_TOP_RIGHT));
        $this->renderBoxLineWithColor($message, $color);
        $this->output->writeln(\sprintf('<fg=%s>%s</>', $color, self::BOX_BOTTOM_LEFT . \str_repeat(self::BOX_HORIZONTAL, $this->boxWidth - 2) . self::BOX_BOTTOM_RIGHT));
    }

    /**
     * Format a date string to human-readable format (e.g., "Jan 15, 2025")
     */
    public function formatDate(string $dateString): string
    {
        if (empty($dateString)) {
            return 'Unknown';
        }

        try {
            $date = new \DateTimeImmutable($dateString);
            return $date->format('M j, Y');
        } catch (\Exception) {
            return $dateString;
        }
    }

    private function renderBoxTop(string $indicator, string $indicatorColor, string $label = ''): void
    {
        $indicatorPart = \sprintf('<fg=%s>%s</>', $indicatorColor, $indicator);

        if ($label !== '') {
            // With label: ╭─● CURRENT ──────────────────────────────────────╮
            $labelDisplay = ' ' . $label . ' ';
            $contentLength = 2 + \mb_strlen($labelDisplay); // "─●" + " LABEL "
            $remainingWidth = $this->boxWidth - 2 - $contentLength; // -2 for corners

            $line = \sprintf(
                '<fg=gray>%s%s</>%s%s<fg=gray>%s%s</>',
                self::BOX_TOP_LEFT,
                self::BOX_HORIZONTAL,
                $indicatorPart,
                Style::highlight($labelDisplay),
                \str_repeat(self::BOX_HORIZONTAL, $remainingWidth),
                self::BOX_TOP_RIGHT,
            );
        } else {
            // Without label: ╭─○─────────────────────────────────────────────╮
            $contentLength = 2; // "─○"
            $remainingWidth = $this->boxWidth - 2 - $contentLength;

            $line = \sprintf(
                '<fg=gray>%s%s</>%s<fg=gray>%s%s</>',
                self::BOX_TOP_LEFT,
                self::BOX_HORIZONTAL,
                $indicatorPart,
                \str_repeat(self::BOX_HORIZONTAL, $remainingWidth),
                self::BOX_TOP_RIGHT,
            );
        }

        $this->output->writeln($line);
    }

    private function renderBoxBottom(): void
    {
        $this->output->writeln(\sprintf(
            '<fg=gray>%s%s%s</>',
            self::BOX_BOTTOM_LEFT,
            \str_repeat(self::BOX_HORIZONTAL, $this->boxWidth - 2),
            self::BOX_BOTTOM_RIGHT,
        ));
    }

    private function renderBoxLine(string $content): void
    {
        $this->output->writeln(\sprintf(
            '<fg=gray>%s</>  %s',
            self::BOX_VERTICAL,
            $content,
        ));
    }

    private function renderBoxLineWithColor(string $content, string $color): void
    {
        $this->output->writeln(\sprintf(
            '<fg=%s>%s</>  %s',
            $color,
            self::BOX_VERTICAL,
            $content,
        ));
    }

    /**
     * @return array<string, string[]>
     */
    private function buildPathToAliasesMap(array $aliases): array
    {
        $map = [];
        foreach ($aliases as $alias => $path) {
            if (!isset($map[$path])) {
                $map[$path] = [];
            }
            $map[$path][] = $alias;
        }
        return $map;
    }
}
