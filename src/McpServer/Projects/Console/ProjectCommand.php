<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Projects\Console;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\Console\BaseCommand;
use Butschster\ContextGenerator\Console\Renderer\Style;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Projects\ProjectServiceInterface;
use Spiral\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question\ChoiceQuestion;

#[AsCommand(
    name: 'project',
    description: 'Manage projects and change the working directory',
)]
final class ProjectCommand extends BaseCommand
{
    #[Argument(
        name: 'path',
        description: 'Path or alias to the project. Use "." for current directory.',
    )]
    protected ?string $path = null;

    public function __invoke(DirectoriesInterface $dirs, ProjectServiceInterface $projectService): int
    {
        $this->outputService->title('Project Management');
        
        // If no path provided, show interactive selection or current project
        if ($this->path === null) {
            $this->outputService->info('No project path specified, entering interactive mode');
            return $this->selectProjectInteractively($projectService);
        }

        // Path specified, display info
        $this->outputService->section('Project Selection');
        $this->outputService->keyValue('Requested Path/Alias', $this->path);
        
        // Handle using an alias as the path
        $resolvedPath = $projectService->resolvePathOrAlias($this->path);
        if ($resolvedPath !== $this->path) {
            $this->logger->info(\sprintf("Resolved alias '%s' to path: %s", $this->path, $resolvedPath));
            $this->outputService->info(\sprintf(
                "Resolved alias %s to path: %s",
                $this->outputService->highlight($this->path, 'bright-cyan'),
                $this->outputService->highlight($resolvedPath, 'bright-green')
            ));
            $this->path = $resolvedPath;
        }

        // Normalize path to absolute path
        $projectPath = $this->normalizePath($this->path, $dirs);
        $this->outputService->keyValue('Absolute Path', $projectPath);

        // Try to switch to this project if it exists
        $this->outputService->section('Project Switch');
        
        if ($projectService->switchToProject($projectPath)) {
            $statusRenderer = $this->outputService->getStatusRenderer();
            $statusRenderer->renderSuccess('Project Switch', 'Successful');
            
            // Display project details
            $project = $projectService->getCurrentProject();
            if ($project !== null) {
                $this->outputService->section('Active Project Details');
                
                $this->outputService->keyValue('Path', $project->path);
                
                if ($project->hasConfigFile()) {
                    $this->outputService->keyValue('Config File', $project->getConfigFile());
                }
                
                if ($project->hasEnvFile()) {
                    $this->outputService->keyValue('Env File', $project->getEnvFile());
                }
                
                $aliases = $projectService->getAliasesForPath($project->path);
                if (!empty($aliases)) {
                    $this->outputService->keyValue('Aliases', \implode(', ', $aliases));
                }
            }
            
            $this->outputService->success(\sprintf(
                "Successfully switched to project: %s",
                $this->outputService->highlight($projectPath, 'bright-cyan')
            ));
            
            // Add helpful tips
            $this->outputService->note([
                'Available commands for this project:',
                '- Use "ctx generate" to generate context files',
                '- Use "ctx project:list" to see all registered projects'
            ]);
            
            return Command::SUCCESS;
        }

        $this->outputService->error(\sprintf(
            "Project path does not exist: %s",
            $this->outputService->highlight($projectPath, 'red')
        ));
        
        // Add helpful information about adding projects
        $this->outputService->note([
            'To add a new project:',
            '- Use "ctx project:add <path>" to register a new project',
            '- Use "ctx project:list" to see all registered projects'
        ]);

        return Command::FAILURE;
    }

    /**
     * Show interactive project selection
     */
    private function selectProjectInteractively(ProjectServiceInterface $projectService): int
    {
        $projects = $projectService->getProjects();
        $currentProject = $projectService->getCurrentProject();

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

        // Show current project first
        if ($currentProject !== null) {
            $this->outputService->section('Current Active Project');
            
            // Use key-value for project details
            $this->outputService->keyValue('Path', $currentProject->path);
            
            if ($currentProject->hasConfigFile()) {
                $this->outputService->keyValue('Config File', $currentProject->getConfigFile());
            }
            
            if ($currentProject->hasEnvFile()) {
                $this->outputService->keyValue('Env File', $currentProject->getEnvFile());
            }
            
            $aliases = $projectService->getAliasesForPath($currentProject->path);
            if (!empty($aliases)) {
                $this->outputService->keyValue('Aliases', \implode(', ', $aliases));
            }
        }

        // Project selection section
        $this->outputService->section('Available Projects');
        
        // Build choice list with formatted options
        $choices = [];
        $choiceMap = []; // Maps display strings back to project paths
        
        // Display available projects as a status list before selection
        $statusList = [];
        foreach ($projects as $path => $projectInfo) {
            $isCurrent = $currentProject && $currentProject->path === $path;
            $aliases = $projectService->getAliasesForPath($path);
            $aliasesText = !empty($aliases) ? '[' . \implode(', ', $aliases) . ']' : '';
            
            $statusList[$path] = [
                'status' => $isCurrent ? 'success' : 'info',
                'message' => $aliasesText . ($isCurrent ? ' (current)' : ''),
            ];
        }
        
        $this->outputService->statusList($statusList);

        foreach ($projects as $path => $projectInfo) {
            $isCurrent = $currentProject && $currentProject->path === $path;
            $aliases = $projectService->getAliasesForPath($path);
            $aliasString = !empty($aliases) ? '[' . \implode(', ', $aliases) . ']' : '';

            $displayString = \sprintf(
                '%s%s',
                ($isCurrent && $aliasString !== '') ? Style::highlight($aliasString, bold: true) : $aliasString,
                $isCurrent ? Style::highlight($path, color: 'bright-blue', bold: true) : $path,
            );

            $choices[] = $displayString;
            $choiceMap[$displayString] = $path;
        }

        // Create the question with all choices
        $helper = $this->getHelper('question');
        \assert($helper instanceof QuestionHelper);
        $question = new ChoiceQuestion(
            'Select a project to switch to:',
            $choices,
            0, // Default to first option
        );
        $question->setErrorMessage('Invalid selection.');

        $selectedChoice = $helper->ask($this->input, $this->output, $question);

        // Get the actual path from the selection
        $selectedPath = $choiceMap[$selectedChoice];

        // Switch to the selected project
        $this->outputService->section('Switching Project');
        
        if ($projectService->switchToProject($selectedPath)) {
            // Display status and success message
            $statusRenderer = $this->outputService->getStatusRenderer();
            $statusRenderer->renderSuccess('Project Switch', 'Successful');
            
            $this->outputService->success(\sprintf(
                "Successfully switched to project: %s",
                $this->outputService->highlight($selectedPath, 'bright-cyan')
            ));
            
            return Command::SUCCESS;
        }

        // This should not happen as we're selecting from existing projects
        $this->outputService->error('Failed to switch to selected project.');
        return Command::FAILURE;
    }

    /**
     * Normalize a path to an absolute path
     */
    private function normalizePath(string $path, DirectoriesInterface $dirs): string
    {
        // Handle special case for current directory
        if ($path === '.') {
            return (string) FSPath::cwd();
        }

        $pathObj = FSPath::create($path);

        // If path is relative, make it absolute from the current directory
        if ($pathObj->isRelative()) {
            $pathObj = $pathObj->absolute();
        }

        return $pathObj->toString();
    }
}
