<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\Console;

use Butschster\ContextGenerator\Console\BaseCommand;
use Butschster\ContextGenerator\Console\Renderer\Style;
use Butschster\ContextGenerator\Research\Service\ResearchServiceInterface;
use Spiral\Console\Attribute\Option;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;

#[AsCommand(
    name: 'researches',
    description: 'List all researches',
    aliases: ['research:list'],
)]
final class ResearchListCommand extends BaseCommand
{
    #[Option(description: 'Filter by status')]
    protected ?string $status = null;

    #[Option(description: 'Filter by template')]
    protected ?string $template = null;

    public function __invoke(ResearchServiceInterface $service): int
    {
        $filters = [];

        if ($this->status !== null) {
            $filters['status'] = $this->status;
        }

        if ($this->template !== null) {
            $filters['template'] = $this->template;
        }

        try {
            $researches = $service->findAll($filters);

            if (empty($researches)) {
                $this->output->info('No researches found.');
                return Command::SUCCESS;
            }

            $this->output->title('Researches');

            $table = new Table($this->output);
            $table->setHeaders(['ID', 'Name', 'Status', 'Template', 'Description', 'Tags']);

            foreach ($researches as $research) {
                $table->addRow([
                    Style::property($research->id),
                    $research->name,
                    $research->status,
                    $research->template,
                    $research->description ?: '-',
                    \implode(', ', $research->tags),
                ]);
            }

            $table->render();

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $this->output->error('Failed to list researches: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
