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
        $projects = $projectService->getProjects();
        $aliases = $projectService->getAliases();
        $currentProject = $projectService->getCurrentProject();

        if (empty($projects)) {
            $this->output->writeln('');
            $this->output->info("No projects registered. Use 'ctx project:add <path>' to add a project.");
            return Command::SUCCESS;
        }

        $this->output->writeln('');

        $renderer = new ProjectRenderer($this->output);
        $renderer->renderProjectList(
            $projects,
            $aliases,
            $currentProject?->path,
        );

        // Render helpful hints
        $renderer->renderHints([
            'ctx project <path|alias>' => 'Switch to a project',
            'ctx project:add <path>' => 'Add a new project',
            'ctx project:remove <path|alias>' => 'Remove a project',
        ]);

        return Command::SUCCESS;
    }
}
