<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Console;

use Butschster\ContextGenerator\Console\BaseCommand;
use Butschster\ContextGenerator\Document\DocumentRegistry;
use Butschster\ContextGenerator\Template\Registry\TemplateRegistry;
use Spiral\Console\Attribute\Option;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(
    name: 'template:list',
    description: 'List available project templates',
)]
final class ListCommand extends BaseCommand
{
    #[Option(
        name: 'tag',
        shortcut: 't',
        description: 'Filter templates by tag (can be used multiple times)',
    )]
    protected array $tags;

    #[Option(
        name: 'detailed',
        shortcut: 'd',
        description: 'Show detailed information about templates',
    )]
    protected bool $detailed = false;

    public function __invoke(TemplateRegistry $templateRegistry): int
    {
        $templates = $templateRegistry->getAllTemplates();

        if (empty($templates)) {
            $this->output->warning('No templates available');
            return Command::SUCCESS;
        }

        // Filter by tags if specified
        if (!empty($this->tags)) {
            $templates = \array_filter($templates, fn($template) => !empty(\array_intersect($this->tags, $template->tags)));
        }

        if (empty($templates)) {
            $this->output->warning(\sprintf(
                'No templates found with tag(s): %s',
                \implode(', ', $this->tags),
            ));
            return Command::SUCCESS;
        }

        $this->output->title('Available Templates');

        if ($this->detailed) {
            return $this->showDetailedList($templates);
        }

        return $this->showBasicList($templates);
    }

    /**
     * Show basic template list in table format
     */
    private function showBasicList(array $templates): int
    {
        $tableData = [];

        foreach ($templates as $template) {
            $tableData[] = [
                $template->name,
                $template->description,
                \implode(', ', $template->tags),
                $template->priority,
            ];
        }

        $this->output->table(['Name', 'Description', 'Tags', 'Priority'], $tableData);

        $this->output->note(\sprintf(
            'Use "ctx init <template-name>" to initialize with a specific template.',
        ));

        return Command::SUCCESS;
    }

    /**
     * Show detailed template information
     */
    private function showDetailedList(array $templates): int
    {
        foreach ($templates as $index => $template) {
            if ($index > 0) {
                $this->output->newLine();
            }

            $this->output->section(\sprintf('%s (%s)', $template->name, $template->description));

            // Show basic info
            $this->output->definitionList(
                ['Priority' => (string) $template->priority],
                ['Tags' => empty($template->tags) ? 'None' : \implode(', ', $template->tags)],
            );

            // Show detection criteria if available
            if (!empty($template->detectionCriteria)) {
                $this->output->writeln('<info>Detection Criteria:</info>');

                // Show files
                if (isset($template->detectionCriteria['files']) && !empty($template->detectionCriteria['files'])) {
                    $this->output->writeln(\sprintf(
                        '  • Required Files: %s',
                        \implode(', ', $template->detectionCriteria['files']),
                    ));
                }

                // Show directories
                if (isset($template->detectionCriteria['directories']) && !empty($template->detectionCriteria['directories'])) {
                    $this->output->writeln(\sprintf(
                        '  • Expected Directories: %s',
                        \implode(', ', $template->detectionCriteria['directories']),
                    ));
                }

                // Show patterns (packages)
                if (isset($template->detectionCriteria['patterns']) && !empty($template->detectionCriteria['patterns'])) {
                    $this->output->writeln(\sprintf(
                        '  • Required Packages: %s',
                        \implode(', ', $template->detectionCriteria['patterns']),
                    ));
                }
            }

            // Show generated documents
            $documents = $template->config?->has('documents')
                ? $template->config->get('documents', DocumentRegistry::class)->getItems()
                : [];

            if (!empty($documents)) {
                $this->output->writeln('<info>Generated Documents:</info>');
                foreach ($documents as $document) {
                    $this->output->writeln(\sprintf(
                        '  • %s → %s',
                        $document->description,
                        $document->outputPath,
                    ));
                }
            }
        }

        $this->output->note([
            'Use "ctx init <template-name>" to initialize with a specific template.',
            'Use "ctx init" to let the system detect the best template automatically.',
            'Use "ctx init --show-all" to see all possible templates with confidence scores.',
        ]);

        return Command::SUCCESS;
    }
}
