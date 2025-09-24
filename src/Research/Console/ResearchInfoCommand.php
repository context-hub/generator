<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\Console;

use Butschster\ContextGenerator\Console\BaseCommand;
use Butschster\ContextGenerator\Console\Renderer\Style;
use Butschster\ContextGenerator\Research\Domain\Model\Research;
use Butschster\ContextGenerator\Research\Domain\Model\Template;
use Butschster\ContextGenerator\Research\Domain\ValueObject\ResearchId;
use Butschster\ContextGenerator\Research\Domain\ValueObject\TemplateKey;
use Butschster\ContextGenerator\Research\Service\EntryServiceInterface;
use Butschster\ContextGenerator\Research\Service\ResearchServiceInterface;
use Butschster\ContextGenerator\Research\Service\TemplateServiceInterface;
use Spiral\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;

#[AsCommand(
    name: 'research',
    description: 'Show detailed information about research',
)]
final class ResearchInfoCommand extends BaseCommand
{
    #[Argument(description: 'Research ID to show information for')]
    protected string $researchId;

    public function __invoke(
        ResearchServiceInterface $service,
        EntryServiceInterface $entryService,
        TemplateServiceInterface $templateService,
    ): int {
        try {
            $researchId = new ResearchId($this->researchId);

            // Get research information
            $research = $service->get($researchId);
            if ($research === null) {
                $this->output->error("Research not found: {$this->researchId}");
                return Command::FAILURE;
            }

            // Get template information
            $template = $templateService->getTemplate(new TemplateKey($research->template));

            // Display research information
            $this->displayInfo($research, $template);

            // Show entries if requested
            $this->displayEntries($entryService, $researchId);

            // Show statistics if requested
            $this->displayStatistics($entryService, $researchId);

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $this->output->error('Failed to get research information: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function displayInfo(Research $research, ?Template $template): void
    {
        $this->output->title("Research Information");

        $this->output->definitionList(
            ['ID', Style::property($research->id)],
            ['Name', $research->name],
            ['Description', $research->description ?: 'None'],
            ['Status', $research->status],
            ['Template', $research->template . ($template ? " ({$template->name})" : ' (template not found)')],
            ['Tags', empty($research->tags) ? 'None' : \implode(', ', $research->tags)],
            ['Entry Directories', empty($research->entryDirs) ? 'None' : \implode(', ', $research->entryDirs)],
            ['Research Path', $research->path ?? 'Not set'],
        );

        if ($template) {
            $this->output->section('Template Information');
            $this->output->definitionList(
                ['Template Name', $template->name],
                ['Template Description', $template->description ?: 'None'],
                ['Template Tags', empty($template->tags) ? 'None' : \implode(', ', $template->tags)],
                ['Categories', \count($template->categories)],
                ['Entry Types', \count($template->entryTypes)],
            );
        }
    }

    private function displayEntries(EntryServiceInterface $entryService, ResearchId $researchId): void
    {
        $this->output->section('Entries');

        try {
            $entries = $entryService->findAll($researchId);

            if (empty($entries)) {
                $this->output->info('No entries found in this research.');
                return;
            }

            $table = new Table($this->output);
            $table->setHeaders(['ID', 'Title', 'Type', 'Category', 'Status', 'Created', 'Updated', 'Tags']);

            foreach ($entries as $entry) {
                $table->addRow([
                    Style::property(\substr($entry->entryId, 0, 8) . '...'),
                    $entry->title,
                    $entry->entryType,
                    $entry->category,
                    $entry->status,
                    $entry->createdAt->format('Y-m-d H:i'),
                    $entry->updatedAt->format('Y-m-d H:i'),
                    empty($entry->tags) ? '-' : \implode(', ', $entry->tags),
                ]);
            }

            $table->render();

        } catch (\Throwable $e) {
            $this->output->error('Failed to load research entries: ' . $e->getMessage());
        }
    }

    private function displayStatistics(EntryServiceInterface $entryService, ResearchId $researchId): void
    {
        $this->output->section('Statistics');

        try {
            $entries = $entryService->findAll($researchId);

            // Calculate statistics
            $totalEntries = \count($entries);
            $entriesByType = [];
            $entriesByCategory = [];
            $entriesByStatus = [];
            $totalContentLength = 0;

            foreach ($entries as $entry) {
                // Count by type
                if (!isset($entriesByType[$entry->entryType])) {
                    $entriesByType[$entry->entryType] = 0;
                }
                $entriesByType[$entry->entryType]++;

                // Count by category
                if (!isset($entriesByCategory[$entry->category])) {
                    $entriesByCategory[$entry->category] = 0;
                }
                $entriesByCategory[$entry->category]++;

                // Count by status
                if (!isset($entriesByStatus[$entry->status])) {
                    $entriesByStatus[$entry->status] = 0;
                }
                $entriesByStatus[$entry->status]++;

                // Content length
                $totalContentLength += \strlen($entry->content);
            }

            $this->output->definitionList(
                ['Total Entries', (string) $totalEntries],
                ['Total Content Length', \number_format($totalContentLength) . ' characters'],
                ['Average Content Length', $totalEntries > 0 ? \number_format($totalContentLength / $totalEntries) . ' characters' : '0'],
            );

            if (!empty($entriesByType)) {
                $this->output->writeln("\n<comment>Entries by Type:</comment>");
                foreach ($entriesByType as $type => $count) {
                    $this->output->writeln("  • {$type}: {$count}");
                }
            }

            if (!empty($entriesByCategory)) {
                $this->output->writeln("\n<comment>Entries by Category:</comment>");
                foreach ($entriesByCategory as $category => $count) {
                    $this->output->writeln("  • {$category}: {$count}");
                }
            }

            if (!empty($entriesByStatus)) {
                $this->output->writeln("\n<comment>Entries by Status:</comment>");
                foreach ($entriesByStatus as $status => $count) {
                    $this->output->writeln("  • {$status}: {$count}");
                }
            }

        } catch (\Throwable $e) {
            $this->output->error('Failed to calculate research statistics: ' . $e->getMessage());
        }
    }
}
