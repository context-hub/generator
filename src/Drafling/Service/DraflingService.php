<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Service;

use Butschster\ContextGenerator\Drafling\Domain\Model\Entry;
use Butschster\ContextGenerator\Drafling\Domain\Model\Project;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\ProjectId;
use Butschster\ContextGenerator\Drafling\Repository\EntryRepositoryInterface;
use Butschster\ContextGenerator\Drafling\Repository\ProjectRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Main Drafling service implementation for orchestrating complex operations
 */
final readonly class DraflingService implements DraflingServiceInterface
{
    public function __construct(
        private ProjectRepositoryInterface $projectRepository,
        private EntryRepositoryInterface $entryRepository,
        private TemplateServiceInterface $templateService,
        private ?LoggerInterface $logger = null,
    ) {}

    #[\Override]
    public function getProjectContent(ProjectId $projectId, array $options = []): array
    {
        $project = $this->projectRepository->findById($projectId);
        if ($project === null) {
            $this->logger?->error('Project not found for content retrieval', [
                'project_id' => $projectId->value,
            ]);
            return [];
        }

        $this->logger?->info('Retrieving project content', [
            'project_id' => $projectId->value,
            'options' => $options,
        ]);

        // Get all entries for the project
        $entries = $this->entryRepository->findByProject($projectId);

        // Group by category if requested
        $groupByCategory = $options['categories'] ?? false;
        $includeMetadata = $options['include_metadata'] ?? true;
        $format = $options['format'] ?? 'full';

        if ($groupByCategory) {
            return $this->groupEntriesByCategory($entries, $format, $includeMetadata);
        }

        return $this->formatEntries($entries, $format, $includeMetadata);
    }

    #[\Override]
    public function getProjectStructure(ProjectId $projectId): array
    {
        $project = $this->projectRepository->findById($projectId);
        if ($project === null) {
            $this->logger?->error('Project not found for structure retrieval', [
                'project_id' => $projectId->value,
            ]);
            return [];
        }

        $this->logger?->info('Retrieving project structure', [
            'project_id' => $projectId->value,
        ]);

        $entries = $this->entryRepository->findByProject($projectId);

        // Get template for structure information
        $templateKey = \Butschster\ContextGenerator\Drafling\Domain\ValueObject\TemplateKey::fromString($project->template);
        $template = $this->templateService->getTemplate($templateKey);

        $structure = [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'template' => $project->template,
                'status' => $project->status,
                'entry_dirs' => $project->entryDirs,
            ],
            'template_info' => $template ? [
                'categories' => \array_map(static fn($cat) => [
                    'name' => $cat->name,
                    'display_name' => $cat->displayName,
                    'allowed_types' => $cat->entryTypes,
                ], $template->categories),
                'entry_types' => \array_map(static fn($type) => [
                    'key' => $type->key,
                    'display_name' => $type->displayName,
                    'statuses' => \array_map(static fn($status) => $status->value, $type->statuses),
                ], $template->entryTypes),
            ] : null,
            'content_summary' => [
                'total_entries' => \count($entries),
                'by_category' => $this->countEntriesByCategory($entries),
                'by_type' => $this->countEntriesByType($entries),
                'by_status' => $this->countEntriesByStatus($entries),
            ],
        ];

        return $structure;
    }

    #[\Override]
    public function getProjectHistory(ProjectId $projectId): array
    {
        $project = $this->projectRepository->findById($projectId);
        if ($project === null) {
            $this->logger?->error('Project not found for history retrieval', [
                'project_id' => $projectId->value,
            ]);
            return [];
        }

        $this->logger?->info('Retrieving project history', [
            'project_id' => $projectId->value,
        ]);

        $entries = $this->entryRepository->findByProject($projectId);

        // Sort entries by creation and update dates to build timeline
        $timeline = [];

        foreach ($entries as $entry) {
            $timeline[] = [
                'type' => 'entry_created',
                'timestamp' => $entry->createdAt,
                'entry_id' => $entry->entryId,
                'entry_title' => $entry->title,
                'category' => $entry->category,
                'entry_type' => $entry->entryType,
            ];

            // Add update event if entry was modified after creation
            if ($entry->updatedAt > $entry->createdAt) {
                $timeline[] = [
                    'type' => 'entry_updated',
                    'timestamp' => $entry->updatedAt,
                    'entry_id' => $entry->entryId,
                    'entry_title' => $entry->title,
                ];
            }
        }

        // Sort timeline by timestamp (newest first)
        \usort($timeline, static fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return [
            'project_id' => $project->id,
            'timeline' => $timeline,
            'summary' => [
                'total_events' => \count($timeline),
                'latest_activity' => $timeline[0]['timestamp'] ?? null,
            ],
        ];
    }

    #[\Override]
    public function validateProject(Project $project): array
    {
        $errors = [];

        $this->logger?->debug('Validating project against template', [
            'project_id' => $project->id,
            'template' => $project->template,
        ]);

        // Check if template exists
        $templateKey = \Butschster\ContextGenerator\Drafling\Domain\ValueObject\TemplateKey::fromString($project->template);
        $template = $this->templateService->getTemplate($templateKey);

        if ($template === null) {
            $errors[] = "Template '{$project->template}' not found";
            return $errors; // Can't validate further without template
        }

        // Validate project structure against template requirements
        if (empty($project->entryDirs)) {
            $errors[] = "Project must have at least one entry directory configured";
        }

        $this->logger?->info('Project validation completed', [
            'project_id' => $project->id,
            'errors_count' => \count($errors),
        ]);

        return $errors;
    }

    #[\Override]
    public function validateEntry(Entry $entry, Project $project): array
    {
        $errors = [];

        $this->logger?->debug('Validating entry against project template', [
            'entry_id' => $entry->entryId,
            'project_id' => $project->id,
            'template' => $project->template,
        ]);

        // Get template
        $templateKey = \Butschster\ContextGenerator\Drafling\Domain\ValueObject\TemplateKey::fromString($project->template);
        $template = $this->templateService->getTemplate($templateKey);

        if ($template === null) {
            $errors[] = "Project template '{$project->template}' not found";
            return $errors;
        }

        // Validate category exists in template
        if (!$template->hasCategory($entry->category)) {
            $errors[] = "Category '{$entry->category}' is not valid for template '{$project->template}'";
        }

        // Validate entry type exists in template
        if (!$template->hasEntryType($entry->entryType)) {
            $errors[] = "Entry type '{$entry->entryType}' is not valid for template '{$project->template}'";
        }

        // Validate entry type is allowed in category
        if (!$template->validateEntryInCategory($entry->category, $entry->entryType)) {
            $errors[] = "Entry type '{$entry->entryType}' is not allowed in category '{$entry->category}'";
        }

        // Validate status is valid for entry type
        $entryType = $template->getEntryType($entry->entryType);
        if ($entryType !== null && !$entryType->hasStatus($entry->status)) {
            $errors[] = "Status '{$entry->status}' is not valid for entry type '{$entry->entryType}'";
        }

        $this->logger?->info('Entry validation completed', [
            'entry_id' => $entry->entryId,
            'errors_count' => \count($errors),
        ]);

        return $errors;
    }

    /**
     * Group entries by category
     */
    private function groupEntriesByCategory(array $entries, string $format, bool $includeMetadata): array
    {
        $grouped = [];

        foreach ($entries as $entry) {
            $category = $entry->category;
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = $this->formatEntry($entry, $format, $includeMetadata);
        }

        return $grouped;
    }

    /**
     * Format entries for output
     */
    private function formatEntries(array $entries, string $format, bool $includeMetadata): array
    {
        return \array_map(
            fn($entry) => $this->formatEntry($entry, $format, $includeMetadata),
            $entries,
        );
    }

    /**
     * Format single entry based on requested format
     */
    private function formatEntry(Entry $entry, string $format, bool $includeMetadata): array
    {
        $formatted = [
            'entry_id' => $entry->entryId,
            'title' => $entry->title,
        ];

        if ($format === 'full') {
            $formatted['content'] = $entry->content;
        } elseif ($format === 'summary') {
            // Truncate content for summary
            $formatted['content_preview'] = \substr($entry->content, 0, 200) . '...';
        }

        if ($includeMetadata) {
            $formatted['metadata'] = [
                'category' => $entry->category,
                'entry_type' => $entry->entryType,
                'status' => $entry->status,
                'tags' => $entry->tags,
                'created_at' => $entry->createdAt->format('c'),
                'updated_at' => $entry->updatedAt->format('c'),
            ];
        }

        return $formatted;
    }

    /**
     * Count entries by category
     */
    private function countEntriesByCategory(array $entries): array
    {
        $counts = [];
        foreach ($entries as $entry) {
            $category = $entry->category;
            $counts[$category] = ($counts[$category] ?? 0) + 1;
        }
        return $counts;
    }

    /**
     * Count entries by type
     */
    private function countEntriesByType(array $entries): array
    {
        $counts = [];
        foreach ($entries as $entry) {
            $type = $entry->entryType;
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }
        return $counts;
    }

    /**
     * Count entries by status
     */
    private function countEntriesByStatus(array $entries): array
    {
        $counts = [];
        foreach ($entries as $entry) {
            $status = $entry->status;
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }
        return $counts;
    }
}
