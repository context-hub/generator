<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Console;

use Butschster\ContextGenerator\Console\BaseCommand;
use Butschster\ContextGenerator\Console\Renderer\Style;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\ProjectId;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\TemplateKey;
use Butschster\ContextGenerator\Drafling\Service\EntryServiceInterface;
use Butschster\ContextGenerator\Drafling\Service\ProjectServiceInterface;
use Butschster\ContextGenerator\Drafling\Service\TemplateServiceInterface;
use Spiral\Console\Attribute\Argument;
use Spiral\Console\Attribute\Option;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;

#[AsCommand(
    name: 'drafling:project',
    description: 'Show detailed information about a Drafling project',
    aliases: ['drafling:project:info'],
)]
final class ProjectInfoCommand extends BaseCommand
{
    #[Argument(
        name: 'project_id',
        description: 'Project ID to show information for',
    )]
    protected string $projectId;

    #[Option(
        name: 'entries',
        shortcut: 'e',
        description: 'Show project entries',
    )]
    protected bool $showEntries = false;

    #[Option(
        name: 'stats',
        shortcut: 's',
        description: 'Show project statistics',
    )]
    protected bool $showStats = false;

    public function __invoke(
        ProjectServiceInterface $projectService,
        EntryServiceInterface $entryService,
        TemplateServiceInterface $templateService,
    ): int {
        try {
            $projectId = new ProjectId($this->projectId);

            // Get project information
            $project = $projectService->getProject($projectId);
            if ($project === null) {
                $this->output->error("Project not found: {$this->projectId}");
                return Command::FAILURE;
            }

            // Get template information
            $template = $templateService->getTemplate(new TemplateKey($project->template));

            // Display project information
            $this->displayProjectInfo($project, $template);

            // Show entries if requested
            if ($this->showEntries) {
                $this->displayProjectEntries($entryService, $projectId);
            }

            // Show statistics if requested
            if ($this->showStats) {
                $this->displayProjectStatistics($entryService, $projectId);
            }

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $this->output->error('Failed to get project information: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function displayProjectInfo($project, $template): void
    {
        $this->output->title("Project Information");

        $this->output->definitionList(
            ['ID', Style::property($project->id)],
            ['Name', $project->name],
            ['Description', $project->description ?: 'None'],
            ['Status', $project->status],
            ['Template', $project->template . ($template ? " ({$template->name})" : ' (template not found)')],
            ['Tags', empty($project->tags) ? 'None' : \implode(', ', $project->tags)],
            ['Entry Directories', empty($project->entryDirs) ? 'None' : \implode(', ', $project->entryDirs)],
            ['Project Path', $project->projectPath ?? 'Not set'],
        );

        if ($template) {
            $this->output->section('Template Information');
            $this->output->definitionList(
                ['Template Name', $template->name],
                ['Template Description', $template->description ?: 'None'],
                ['Template Tags', empty($template->tags) ? 'None' : \implode(', ', $template->tags)],
                ['Categories', \count($template->categories)],
                ['Entry Types', \count($template->entryTypes)],
            );
        }
    }

    private function displayProjectEntries(EntryServiceInterface $entryService, ProjectId $projectId): void
    {
        $this->output->section('Project Entries');

        try {
            $entries = $entryService->getEntries($projectId);

            if (empty($entries)) {
                $this->output->info('No entries found in this project.');
                return;
            }

            $table = new Table($this->output);
            $table->setHeaders(['ID', 'Title', 'Type', 'Category', 'Status', 'Created', 'Updated', 'Tags']);

            foreach ($entries as $entry) {
                $table->addRow([
                    Style::property(\substr($entry->entryId, 0, 8) . '...'),
                    $entry->title,
                    $entry->entryType,
                    $entry->category,
                    $entry->status,
                    $entry->createdAt->format('Y-m-d H:i'),
                    $entry->updatedAt->format('Y-m-d H:i'),
                    empty($entry->tags) ? '-' : \implode(', ', $entry->tags),
                ]);
            }

            $table->render();

        } catch (\Throwable $e) {
            $this->output->error('Failed to load project entries: ' . $e->getMessage());
        }
    }

    private function displayProjectStatistics(EntryServiceInterface $entryService, ProjectId $projectId): void
    {
        $this->output->section('Project Statistics');

        try {
            $entries = $entryService->getEntries($projectId);

            // Calculate statistics
            $totalEntries = \count($entries);
            $entriesByType = [];
            $entriesByCategory = [];
            $entriesByStatus = [];
            $totalContentLength = 0;

            foreach ($entries as $entry) {
                // Count by type
                if (!isset($entriesByType[$entry->entryType])) {
                    $entriesByType[$entry->entryType] = 0;
                }
                $entriesByType[$entry->entryType]++;

                // Count by category
                if (!isset($entriesByCategory[$entry->category])) {
                    $entriesByCategory[$entry->category] = 0;
                }
                $entriesByCategory[$entry->category]++;

                // Count by status
                if (!isset($entriesByStatus[$entry->status])) {
                    $entriesByStatus[$entry->status] = 0;
                }
                $entriesByStatus[$entry->status]++;

                // Content length
                $totalContentLength += \strlen($entry->content);
            }

            $this->output->definitionList(
                ['Total Entries', (string) $totalEntries],
                ['Total Content Length', \number_format($totalContentLength) . ' characters'],
                ['Average Content Length', $totalEntries > 0 ? \number_format($totalContentLength / $totalEntries) . ' characters' : '0'],
            );

            if (!empty($entriesByType)) {
                $this->output->writeln("\n<comment>Entries by Type:</comment>");
                foreach ($entriesByType as $type => $count) {
                    $this->output->writeln("  • {$type}: {$count}");
                }
            }

            if (!empty($entriesByCategory)) {
                $this->output->writeln("\n<comment>Entries by Category:</comment>");
                foreach ($entriesByCategory as $category => $count) {
                    $this->output->writeln("  • {$category}: {$count}");
                }
            }

            if (!empty($entriesByStatus)) {
                $this->output->writeln("\n<comment>Entries by Status:</comment>");
                foreach ($entriesByStatus as $status => $count) {
                    $this->output->writeln("  • {$status}: {$count}");
                }
            }

        } catch (\Throwable $e) {
            $this->output->error('Failed to calculate project statistics: ' . $e->getMessage());
        }
    }
}
