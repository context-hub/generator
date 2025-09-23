<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Console;

use Butschster\ContextGenerator\Console\BaseCommand;
use Butschster\ContextGenerator\Console\Renderer\Style;
use Butschster\ContextGenerator\Drafling\Service\TemplateServiceInterface;
use Spiral\Console\Attribute\Option;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;

#[AsCommand(
    name: 'drafling:templates',
    description: 'List all Drafling templates',
    aliases: ['drafling-templates'],
)]
final class TemplateListCommand extends BaseCommand
{
    #[Option(
        name: 'tag',
        description: 'Filter templates by tag',
    )]
    protected ?string $tag = null;

    #[Option(
        name: 'name',
        description: 'Filter templates by name (partial match)',
    )]
    protected ?string $nameFilter = null;

    #[Option(
        name: 'details',
        description: 'Include detailed template information',
    )]
    protected bool $details = false;

    public function __invoke(TemplateServiceInterface $templateService): int
    {
        try {
            $templates = $templateService->getAllTemplates();

            // Apply filters
            if ($this->tag !== null) {
                $templates = \array_filter(
                    $templates,
                    fn($template) =>
                    \in_array($this->tag, $template->tags, true),
                );
            }

            if ($this->nameFilter !== null) {
                $searchTerm = \strtolower(\trim($this->nameFilter));
                $templates = \array_filter(
                    $templates,
                    static fn($template) =>
                    \str_contains(\strtolower($template->name), $searchTerm),
                );
            }

            if (empty($templates)) {
                $this->output->info('No Drafling templates found.');
                return Command::SUCCESS;
            }

            $this->output->title('Drafling Templates');

            if ($this->details) {
                foreach ($templates as $template) {
                    $this->displayTemplateDetails($template);
                }
            } else {
                $table = new Table($this->output);
                $table->setHeaders(['ID', 'Name', 'Description', 'Tags']);

                foreach ($templates as $template) {
                    $table->addRow([
                        Style::property($template->key),
                        $template->name,
                        $template->description ?: '-',
                        \implode(', ', $template->tags),
                    ]);
                }

                $table->render();
            }

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $this->output->error('Failed to list templates: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function displayTemplateDetails($template): void
    {
        $this->output->section($template->name);
        $this->output->writeln("ID: " . Style::property($template->key));
        $this->output->writeln("Description: " . ($template->description ?: 'None'));
        $this->output->writeln("Tags: " . \implode(', ', $template->tags));

        if (!empty($template->categories)) {
            $this->output->writeln("\nCategories:");
            foreach ($template->categories as $category) {
                $this->output->writeln("  • {$category->displayName} ({$category->name})");
                if (!empty($category->entryTypes)) {
                    $this->output->writeln("    Entry types: " . \implode(', ', $category->entryTypes));
                }
            }
        }

        if (!empty($template->entryTypes)) {
            $this->output->writeln("\nEntry Types:");
            foreach ($template->entryTypes as $entryType) {
                $this->output->writeln("  • {$entryType->displayName} ({$entryType->key})");
                $this->output->writeln("    Content type: {$entryType->contentType}");
                if (!empty($entryType->statuses)) {
                    $statuses = \array_map(static fn($status) => $status->displayName, $entryType->statuses);
                    $this->output->writeln("    Statuses: " . \implode(', ', $statuses));
                }
            }
        }

        if ($template->prompt !== null) {
            $this->output->writeln("\nPrompt: {$template->prompt}");
        }

        $this->output->newLine();
    }
}
