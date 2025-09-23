<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Console;

use Butschster\ContextGenerator\Console\BaseCommand;
use Butschster\ContextGenerator\Console\Renderer\Style;
use Butschster\ContextGenerator\Drafling\Service\ProjectServiceInterface;
use Spiral\Console\Attribute\Option;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;

#[AsCommand(
    name: 'drafling:projects',
    description: 'List all Drafling projects',
    aliases: ['drafling-projects'],
)]
final class ProjectListCommand extends BaseCommand
{
    #[Option(
        name: 'status',
        description: 'Filter projects by status',
    )]
    protected ?string $status = null;

    #[Option(
        name: 'template',
        description: 'Filter projects by template',
    )]
    protected ?string $template = null;

    public function __invoke(ProjectServiceInterface $projectService): int
    {
        $filters = [];

        if ($this->status !== null) {
            $filters['status'] = $this->status;
        }

        if ($this->template !== null) {
            $filters['template'] = $this->template;
        }

        try {
            $projects = $projectService->listProjects($filters);

            if (empty($projects)) {
                $this->output->info('No Drafling projects found.');
                return Command::SUCCESS;
            }

            $this->output->title('Drafling Projects');

            $table = new Table($this->output);
            $table->setHeaders(['ID', 'Name', 'Status', 'Template', 'Description', 'Tags']);

            foreach ($projects as $project) {
                $table->addRow([
                    Style::property($project->id),
                    $project->name,
                    $project->status,
                    $project->template,
                    $project->description ?: '-',
                    \implode(', ', $project->tags),
                ]);
            }

            $table->render();

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $this->output->error('Failed to list projects: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
