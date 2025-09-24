<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\Console;

use Butschster\ContextGenerator\Console\BaseCommand;
use Butschster\ContextGenerator\Console\Renderer\Style;
use Butschster\ContextGenerator\Research\Domain\Model\Template;
use Butschster\ContextGenerator\Research\Service\TemplateServiceInterface;
use Spiral\Console\Attribute\Option;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(
    name: 'research:templates',
    description: 'List all research templates',
)]
final class TemplateListCommand extends BaseCommand
{
    #[Option(description: 'Filter templates by tag')]
    protected ?string $tag = null;

    #[Option(description: 'Filter templates by name (partial match)')]
    protected ?string $nameFilter = null;

    public function __invoke(TemplateServiceInterface $templateService): int
    {
        try {
            $templates = $templateService->findAll();

            // Apply filters
            if ($this->tag !== null) {
                $templates = \array_filter(
                    $templates,
                    fn(Template $template) =>
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
                $this->output->info('No templates found.');
                return Command::SUCCESS;
            }

            $this->output->title('Templates');

            foreach ($templates as $template) {
                $this->displayDetails($template);
            }

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $this->output->error('Failed to list templates: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function displayDetails(Template $template): void
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

        $this->output->newLine();
    }
}
