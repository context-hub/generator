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
        // If no path provided, show interactive selection or current project
        if ($this->path === null) {
            return $this->selectProjectInteractively($projectService);
        }

        // Handle using an alias as the path
        $resolvedPath = $projectService->resolvePathOrAlias($this->path);
        if ($resolvedPath !== $this->path) {
            $this->logger->info(\sprintf("Resolved alias '%s' to path: %s", $this->path, $resolvedPath));
            $this->path = $resolvedPath;
        }

        // Normalize path to absolute path
        $projectPath = $this->normalizePath($this->path, $dirs);

        // First, try to switch to this project if it exists
        if ($projectService->switchToProject($projectPath)) {
            $this->output->success(\sprintf("Switched to project: %s", $projectPath));
            return Command::SUCCESS;
        }

        $this->output->error(\sprintf("Project path does not exist: %s", $projectPath));

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
            $this->output->info("No projects registered. Use 'ctx project <path>' to add a project.");
            return Command::SUCCESS;
        }

        // Show current project first
        if ($currentProject !== null) {
            $this->output->title('Current Project');

            $this->output->writeln(Style::lineWithTitle('Path', $currentProject->path));

            if ($currentProject->hasConfigFile()) {
                $this->output->writeln(Style::lineWithTitle('Config File', $currentProject->getConfigFile()));
            }

            if ($currentProject->hasEnvFile()) {
                $this->output->writeln(Style::lineWithTitle('Env File', $currentProject->getEnvFile()));
            }

            $aliases = $projectService->getAliasesForPath($currentProject->path);
            if (!empty($aliases)) {
                $this->output->writeln(Style::lineWithTitle('Aliases', \implode(', ', $aliases)));
            }

            $this->output->newLine();
        }

        // Build choice list with formatted options
        $choices = [];
        $choiceMap = []; // Maps display strings back to project paths

        foreach ($projects as $path => $_) {
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
        if ($projectService->switchToProject($selectedPath)) {
            $this->output->success(\sprintf("Switched to project: %s", $selectedPath));
            return Command::SUCCESS;
        }

        // This should not happen as we're selecting from existing projects
        $this->output->error('Failed to switch to selected project.');
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
