<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Projects\Console;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\Console\BaseCommand;
use Butschster\ContextGenerator\Console\Renderer\ProjectRenderer;
use Butschster\ContextGenerator\Console\Renderer\Style;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Projects\ProjectServiceInterface;
use Spiral\Console\Attribute\Argument;
use Spiral\Console\Attribute\Option;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(
    name: 'project:remove',
    description: 'Remove a project from the registry',
)]
final class ProjectRemoveCommand extends BaseCommand
{
    #[Argument(
        name: 'path',
        description: 'Path or alias to the project. Use "." for current directory.',
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
        description: 'Keep aliases when removing project (by default aliases are removed)',
    )]
    protected bool $keepAliases = false;

    public function __invoke(DirectoriesInterface $dirs, ProjectServiceInterface $projectService): int
    {
        // If no path provided, show interactive selection
        if ($this->path === null) {
            return $this->selectProjectInteractively($projectService);
        }

        // Resolve alias to path
        $resolvedPath = $projectService->resolvePathOrAlias($this->path);

        // Normalize path
        $projectPath = $this->normalizePath($resolvedPath, $dirs);

        // Check if project exists
        if (!$projectService->hasProject($projectPath)) {
            $this->output->error(\sprintf("Project not found: %s", $this->path));
            return Command::FAILURE;
        }

        return $this->removeProject($projectService, $projectPath);
    }

    private function selectProjectInteractively(ProjectServiceInterface $projectService): int
    {
        $projects = $projectService->getProjects();
        $currentProject = $projectService->getCurrentProject();

        if (empty($projects)) {
            $this->output->writeln('');
            $this->output->info("No projects registered.");
            return Command::SUCCESS;
        }

        $this->output->writeln('');

        // Build choice list
        $choices = [];
        $choiceMap = [];

        foreach ($projects as $path => $_) {
            $aliases = $projectService->getAliasesForPath($path);
            $aliasString = !empty($aliases) ? ' [' . \implode(', ', $aliases) . ']' : '';
            $isCurrent = ($currentProject && $currentProject->path === $path);
            $indicator = $isCurrent ? Style::success('●') : Style::muted('○');

            $displayString = \sprintf('%s %s%s', $indicator, $path, $aliasString);
            $choices[] = $displayString;
            $choiceMap[$displayString] = $path;
        }

        // Add cancel option
        $cancelOption = Style::muted('  Cancel');
        $choices[] = $cancelOption;

        $helper = $this->getHelper('question');
        \assert($helper instanceof QuestionHelper);

        $question = new ChoiceQuestion('Select a project to remove:', $choices, \count($choices) - 1);
        $question->setErrorMessage('Invalid selection.');

        $selectedChoice = $helper->ask($this->input, $this->output, $question);

        if ($selectedChoice === $cancelOption) {
            $this->output->writeln('');
            $this->output->writeln(Style::muted('  Operation cancelled.'));
            $this->output->writeln('');
            return Command::SUCCESS;
        }

        $selectedPath = $choiceMap[$selectedChoice];

        return $this->removeProject($projectService, $selectedPath);
    }

    private function removeProject(ProjectServiceInterface $projectService, string $projectPath): int
    {
        $projects = $projectService->getProjects();
        $currentProject = $projectService->getCurrentProject();
        $isCurrentProject = $currentProject && $currentProject->path === $projectPath;
        $aliases = $projectService->getAliasesForPath($projectPath);
        $project = $projects[$projectPath];

        $this->output->writeln('');

        // Show project card that will be removed
        $renderer = new ProjectRenderer($this->output);
        $renderer->renderProjectCard($projectPath, $project, $aliases, $isCurrentProject);

        if ($isCurrentProject) {
            $this->output->writeln('');
            $this->output->writeln(\sprintf(
                '  %s This is the current project. It will be unset after removal.',
                Style::warning('!'),
            ));
        }

        // Confirm removal unless --force is set
        if (!$this->force) {
            $this->output->writeln('');

            $helper = $this->getHelper('question');
            \assert($helper instanceof QuestionHelper);

            $confirmQuestion = new ConfirmationQuestion(
                '  Are you sure you want to remove this project? [y/N] ',
                false,
            );

            if (!$helper->ask($this->input, $this->output, $confirmQuestion)) {
                $this->output->writeln('');
                $this->output->writeln(Style::muted('  Operation cancelled.'));
                $this->output->writeln('');
                return Command::SUCCESS;
            }
        }

        // Handle --keep-aliases warning
        if ($this->keepAliases && !empty($aliases)) {
            $this->output->writeln('');
            $this->output->writeln(\sprintf(
                '  %s --keep-aliases is not meaningful. Aliases point to the removed path.',
                Style::warning('!'),
            ));
        }

        $projectService->removeProject($projectPath);

        $this->output->writeln('');
        $this->output->writeln(\sprintf(
            '  %s Project removed: %s',
            Style::success('✓'),
            Style::path($projectPath),
        ));

        if (!empty($aliases) && !$this->keepAliases) {
            $this->output->writeln(\sprintf(
                '  %s Aliases removed: %s',
                Style::muted('→'),
                Style::label(\implode(', ', $aliases)),
            ));
        }

        // If this was the current project, suggest switching to another
        if ($isCurrentProject) {
            $remainingProjects = $projectService->getProjects();
            if (!empty($remainingProjects)) {
                $firstProjectPath = \array_key_first($remainingProjects);
                $firstProjectAliases = $projectService->getAliasesForPath($firstProjectPath);
                $suggestion = !empty($firstProjectAliases) ? $firstProjectAliases[0] : $firstProjectPath;

                $this->output->writeln('');
                $this->output->writeln(\sprintf(
                    '  %s Use %s to switch to another project',
                    Style::info('→'),
                    Style::command("ctx project {$suggestion}"),
                ));
            }
        }

        $this->output->writeln('');

        return Command::SUCCESS;
    }

    private function normalizePath(string $path, DirectoriesInterface $dirs): string
    {
        if ($path === '.') {
            return (string) FSPath::cwd();
        }

        $pathObj = FSPath::create($path);

        if ($pathObj->isRelative()) {
            $pathObj = $pathObj->absolute();
        }

        return $pathObj->toString();
    }
}
