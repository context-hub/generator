<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Projects\Console;

use Butschster\ContextGenerator\Console\BaseCommand;
use Butschster\ContextGenerator\Console\Renderer\Style;
use Butschster\ContextGenerator\McpServer\Projects\ProjectServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;

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
            $this->output->info("No projects registered. Use 'ctx project <path>' to add a project.");
            return Command::SUCCESS;
        }

        $this->output->title("Registered Projects");

        // Create inverse alias map for quick lookups
        $pathToAliases = [];
        foreach ($aliases as $alias => $path) {
            if (!isset($pathToAliases[$path])) {
                $pathToAliases[$path] = [];
            }
            $pathToAliases[$path][] = $alias;
        }

        // Create and configure table
        $table = new Table($this->output);
        $table->setHeaders(['Path', 'Config File', 'Env File', 'Aliases', 'Added', 'Current']);

        // Add rows to table
        foreach ($projects as $path => $info) {
            $isCurrent = $currentProject && $currentProject->path === $path ? '✓' : '';

            $aliasesStr = !empty($pathToAliases[$path])
                ? \implode(', ', $pathToAliases[$path])
                : '';

            $table->addRow([
                Style::property($path),
                $info->configFile ?? '',
                $info->envFile ?? '',
                $aliasesStr,
                $info->addedAt,
                $isCurrent ? Style::highlight($isCurrent) : '',
            ]);
        }

        // Render table
        $table->render();

        return Command::SUCCESS;
    }
}
