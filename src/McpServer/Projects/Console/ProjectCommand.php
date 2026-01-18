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
            $this->output->writeln('');
            $this->output->writeln(\sprintf(
                '  %s Switched to project: %s',
                Style::success('✓'),
                Style::path($projectPath),
            ));

            $aliases = $projectService->getAliasesForPath($projectPath);
            if (!empty($aliases)) {
                $this->output->writeln(\sprintf(
                    '    %s %s',
                    Style::muted('Aliases:'),
                    Style::label(\implode(', ', $aliases)),
                ));
            }

            $this->output->writeln('');
            return Command::SUCCESS;
        }

        $this->output->error(\sprintf("Project not found: %s", $this->path));
        return Command::FAILURE;
    }

    private function selectProjectInteractively(ProjectServiceInterface $projectService): int
    {
        $projects = $projectService->getProjects();
        $currentProject = $projectService->getCurrentProject();

        if (empty($projects)) {
            $this->output->writeln('');
            $this->output->info("No projects registered. Use 'ctx project:add <path>' to add a project.");
            return Command::SUCCESS;
        }

        // Show current project card if set
        if ($currentProject !== null) {
            $this->output->writeln('');
            $renderer = new ProjectRenderer($this->output);
            $project = $projects[$currentProject->path] ?? null;

            if ($project !== null) {
                $aliases = $projectService->getAliasesForPath($currentProject->path);
                $renderer->renderProjectCard($currentProject->path, $project, $aliases, true);
                $this->output->writeln('');
            }
        }

        // Build choice list
        $choices = [];
        $choiceMap = [];
        $i = 1;

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

        $this->output->writeln(Style::muted('Navigate with ↑↓ arrows, press Enter to select.'));
        $this->output->writeln(Style::muted('Tip: You can also run ') . Style::command('ctx project <alias>') . Style::muted(' or ') . Style::command('ctx project .') . Style::muted(' for current folder.'));
        $this->output->writeln('');

        $question = new ChoiceQuestion(
            'Select a project to switch to:',
            $choices,
            \count($choices) - 1,
        );
        $question->setErrorMessage('Invalid selection.');

        $selectedChoice = $helper->ask($this->input, $this->output, $question);

        if ($selectedChoice === $cancelOption) {
            $this->output->writeln('');
            $this->output->writeln(Style::muted('  Operation cancelled.'));
            $this->output->writeln('');
            return Command::SUCCESS;
        }

        $selectedPath = $choiceMap[$selectedChoice];

        if ($projectService->switchToProject($selectedPath)) {
            $this->output->writeln('');
            $this->output->writeln(\sprintf(
                '  %s Switched to: %s',
                Style::success('✓'),
                Style::path($selectedPath),
            ));
            $this->output->writeln('');
            return Command::SUCCESS;
        }

        $this->output->error('Failed to switch to selected project.');
        return Command::FAILURE;
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
