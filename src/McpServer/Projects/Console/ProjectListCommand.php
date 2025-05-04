<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Projects\Console;

use Butschster\ContextGenerator\Console\BaseCommand;
use Butschster\ContextGenerator\Console\Renderer\Style;
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

        // Display command title
        $this->outputService->title('Project Registry');

        if (empty($projects)) {
            $this->outputService->info("No projects registered.");
            
            // Show help for adding projects
            $this->outputService->section('Getting Started');
            $listRenderer = $this->outputService->getListRenderer();
            $listRenderer->renderNumberedList([
                'Navigate to your project directory',
                \sprintf('Use %s to add your project', $this->outputService->highlight('ctx project:add .', 'bright-cyan')),
                'Optionally provide an alias with --name=<alias>'
            ]);
            
            return Command::SUCCESS;
        }

        // Show current project if set
        if ($currentProject !== null) {
            $this->outputService->section('Current Active Project');
            
            // Use key-value pairs for better formatting
            $this->outputService->keyValue('Path', $currentProject->path);
            
            if ($currentProject->hasConfigFile()) {
                $this->outputService->keyValue('Config File', $currentProject->getConfigFile());
            }
            
            if ($currentProject->hasEnvFile()) {
                $this->outputService->keyValue('Env File', $currentProject->getEnvFile());
            }
            
            $currentAliases = $projectService->getAliasesForPath($currentProject->path);
            if (!empty($currentAliases)) {
                $this->outputService->keyValue('Aliases', \implode(', ', $currentAliases));
            }
            
            $this->outputService->separator();
        }

        // Create inverse alias map for quick lookups
        $pathToAliases = [];
        foreach ($aliases as $alias => $path) {
            if (!isset($pathToAliases[$path])) {
                $pathToAliases[$path] = [];
            }
            $pathToAliases[$path][] = $alias;
        }

        // Display all projects section
        $this->outputService->section('All Registered Projects');

        // Use the TableRenderer for better styling
        $tableRenderer = $this->outputService->getTableRenderer();
        
        // Create styled headers
        $headers = ['Path', 'Config File', 'Env File', 'Aliases', 'Added', 'Current'];
        $styledHeaders = $tableRenderer->createStyledHeaderRow($headers);
        
        // Prepare rows with styled cells
        $rows = [];
        foreach ($projects as $path => $info) {
            $isCurrent = $currentProject && $currentProject->path === $path;
            $currentCell = $isCurrent ? 
                $tableRenderer->createStatusCell('active') : 
                '';

            $aliasesStr = !empty($pathToAliases[$path])
                ? \implode(', ', $pathToAliases[$path])
                : '';

            $rows[] = [
                $tableRenderer->createPropertyCell($path),
                $info->configFile ?? '',
                $info->envFile ?? '',
                $aliasesStr,
                $info->addedAt,
                $currentCell,
            ];
        }

        // Render the table with separators between rows
        $tableRenderer->render($styledHeaders, $rows, null, true);
        
        // Display summary statistics
        $this->outputService->section('Summary');
        
        $summaryRenderer = $this->outputService->getSummaryRenderer();
        $summaryRenderer->renderStatsSummary('Projects', [
            'Total Projects' => \count($projects),
            'Total Aliases' => \count($aliases),
            'Current Project' => $currentProject ? $currentProject->path : 'None'
        ]);
        
        // Display available commands
        $this->outputService->note([
            'Available commands:',
            '- Use "ctx project <path>" to switch to a project',
            '- Use "ctx project:add <path>" to add a new project',
            '- Use "ctx project" (without arguments) for interactive selection'
        ]);

        return Command::SUCCESS;
    }
}
