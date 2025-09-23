<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Service;

use Butschster\ContextGenerator\Drafling\Domain\Model\Entry;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\EntryId;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\ProjectId;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\TemplateKey;
use Butschster\ContextGenerator\Drafling\Exception\DraflingException;
use Butschster\ContextGenerator\Drafling\Exception\EntryNotFoundException;
use Butschster\ContextGenerator\Drafling\Exception\ProjectNotFoundException;
use Butschster\ContextGenerator\Drafling\Exception\TemplateNotFoundException;
use Butschster\ContextGenerator\Drafling\MCP\DTO\EntryCreateRequest;
use Butschster\ContextGenerator\Drafling\MCP\DTO\EntryUpdateRequest;
use Butschster\ContextGenerator\Drafling\Repository\EntryRepositoryInterface;
use Butschster\ContextGenerator\Drafling\Repository\ProjectRepositoryInterface;
use Butschster\ContextGenerator\Drafling\Storage\StorageDriverInterface;
use Psr\Log\LoggerInterface;

/**
 * Entry service implementation for entry lifecycle management with template validation
 */
final readonly class EntryService implements EntryServiceInterface
{
    public function __construct(
        private EntryRepositoryInterface $entryRepository,
        private ProjectRepositoryInterface $projectRepository,
        private TemplateServiceInterface $templateService,
        private StorageDriverInterface $storageDriver,
        private ?LoggerInterface $logger = null,
    ) {}

    #[\Override]
    public function createEntry(ProjectId $projectId, EntryCreateRequest $request): Entry
    {
        $this->logger?->info('Creating new entry', [
            'project_id' => $projectId->value,
            'category' => $request->category,
            'entry_type' => $request->entryType,
        ]);

        // Verify project exists
        $project = $this->projectRepository->findById($projectId);
        if ($project === null) {
            $error = "Project '{$projectId->value}' not found";
            $this->logger?->error($error, [
                'project_id' => $projectId->value,
            ]);
            throw new ProjectNotFoundException($error);
        }

        // Get and validate template
        $templateKey = TemplateKey::fromString($project->template);
        $template = $this->templateService->getTemplate($templateKey);
        if ($template === null) {
            $error = "Template '{$project->template}' not found";
            $this->logger?->error($error, [
                'project_id' => $projectId->value,
                'template' => $project->template,
            ]);
            throw new TemplateNotFoundException($error);
        }

        // Resolve display names to internal keys
        $resolvedCategory = $this->templateService->resolveCategoryKey($template, $request->category);
        if ($resolvedCategory === null) {
            $error = "Category '{$request->category}' not found in template '{$project->template}'";
            $this->logger?->error($error, [
                'project_id' => $projectId->value,
                'category' => $request->category,
                'template' => $project->template,
            ]);
            throw new DraflingException($error);
        }

        $resolvedEntryType = $this->templateService->resolveEntryTypeKey($template, $request->entryType);
        if ($resolvedEntryType === null) {
            $error = "Entry type '{$request->entryType}' not found in template '{$project->template}'";
            $this->logger?->error($error, [
                'project_id' => $projectId->value,
                'entry_type' => $request->entryType,
                'template' => $project->template,
            ]);
            throw new DraflingException($error);
        }

        // Validate entry type is allowed in category
        if (!$template->validateEntryInCategory($resolvedCategory, $resolvedEntryType)) {
            $error = "Entry type '{$request->entryType}' is not allowed in category '{$request->category}'";
            $this->logger?->error($error, [
                'project_id' => $projectId->value,
                'category' => $request->category,
                'entry_type' => $request->entryType,
            ]);
            throw new DraflingException($error);
        }

        // Resolve status if provided, otherwise use entry type default
        $resolvedStatus = null;
        if ($request->status !== null) {
            $resolvedStatus = $this->templateService->resolveStatusValue($template, $resolvedEntryType, $request->status);
            if ($resolvedStatus === null) {
                $error = "Status '{$request->status}' not found for entry type '{$request->entryType}'";
                $this->logger?->error($error, [
                    'project_id' => $projectId->value,
                    'status' => $request->status,
                    'entry_type' => $request->entryType,
                ]);
                throw new DraflingException($error);
            }
        } else {
            // Use default status from entry type
            $entryType = $template->getEntryType($resolvedEntryType);
            $resolvedStatus = $entryType?->defaultStatus;
        }

        try {
            // Create request with resolved keys
            $resolvedRequest = $request->withResolvedKeys(
                $resolvedCategory,
                $resolvedEntryType,
                $resolvedStatus,
            );

            // Use storage driver to create the entry
            $entry = $this->storageDriver->createEntry($projectId, $resolvedRequest);

            // Save entry to repository
            $this->entryRepository->save($projectId, $entry);

            $this->logger?->info('Entry created successfully', [
                'project_id' => $projectId->value,
                'entry_id' => $entry->entryId,
                'title' => $entry->title,
                'category' => $entry->category,
                'entry_type' => $entry->entryType,
            ]);

            return $entry;

        } catch (\Throwable $e) {
            $this->logger?->error('Failed to create entry', [
                'project_id' => $projectId->value,
                'error' => $e->getMessage(),
            ]);

            throw new DraflingException(
                "Failed to create entry: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    #[\Override]
    public function updateEntry(ProjectId $projectId, EntryId $entryId, EntryUpdateRequest $request): Entry
    {
        $this->logger?->info('Updating entry', [
            'project_id' => $projectId->value,
            'entry_id' => $entryId->value,
            'has_title' => $request->title !== null,
            'has_content' => $request->content !== null,
            'has_status' => $request->status !== null,
        ]);

        // Verify project exists
        $project = $this->projectRepository->findById($projectId);
        if ($project === null) {
            $error = "Project '{$projectId->value}' not found";
            $this->logger?->error($error, [
                'project_id' => $projectId->value,
            ]);
            throw new ProjectNotFoundException($error);
        }

        // Verify entry exists
        $existingEntry = $this->entryRepository->findById($projectId, $entryId);
        if ($existingEntry === null) {
            $error = "Entry '{$entryId->value}' not found in project '{$projectId->value}'";
            $this->logger?->error($error, [
                'project_id' => $projectId->value,
                'entry_id' => $entryId->value,
            ]);
            throw new EntryNotFoundException($error);
        }

        // Resolve status if provided
        $resolvedStatus = $request->status;
        if ($request->status !== null) {
            $templateKey = TemplateKey::fromString($project->template);
            $template = $this->templateService->getTemplate($templateKey);

            if ($template !== null) {
                $resolvedStatusValue = $this->templateService->resolveStatusValue(
                    $template,
                    $existingEntry->entryType,
                    $request->status,
                );

                if ($resolvedStatusValue === null) {
                    $error = "Status '{$request->status}' not found for entry type '{$existingEntry->entryType}'";
                    $this->logger?->error($error, [
                        'project_id' => $projectId->value,
                        'entry_id' => $entryId->value,
                        'status' => $request->status,
                        'entry_type' => $existingEntry->entryType,
                    ]);
                    throw new DraflingException($error);
                }

                $resolvedStatus = $resolvedStatusValue;
            }
        }

        try {
            // Create request with resolved status
            $resolvedRequest = $request->withResolvedStatus($resolvedStatus);

            // Use storage driver to update the entry
            $updatedEntry = $this->storageDriver->updateEntry($projectId, $entryId, $resolvedRequest);

            // Save updated entry to repository
            $this->entryRepository->save($projectId, $updatedEntry);

            $this->logger?->info('Entry updated successfully', [
                'project_id' => $projectId->value,
                'entry_id' => $entryId->value,
                'title' => $updatedEntry->title,
            ]);

            return $updatedEntry;

        } catch (\Throwable $e) {
            $this->logger?->error('Failed to update entry', [
                'project_id' => $projectId->value,
                'entry_id' => $entryId->value,
                'error' => $e->getMessage(),
            ]);

            throw new DraflingException(
                "Failed to update entry: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    #[\Override]
    public function entryExists(ProjectId $projectId, EntryId $entryId): bool
    {
        $exists = $this->entryRepository->exists($projectId, $entryId);

        $this->logger?->debug('Checking entry existence', [
            'project_id' => $projectId->value,
            'entry_id' => $entryId->value,
            'exists' => $exists,
        ]);

        return $exists;
    }

    #[\Override]
    public function getEntry(ProjectId $projectId, EntryId $entryId): ?Entry
    {
        $this->logger?->info('Retrieving single entry', [
            'project_id' => $projectId->value,
            'entry_id' => $entryId->value,
        ]);

        // Verify project exists
        if (!$this->projectRepository->exists($projectId)) {
            $error = "Project '{$projectId->value}' not found";
            $this->logger?->error($error, [
                'project_id' => $projectId->value,
            ]);
            throw new ProjectNotFoundException($error);
        }

        try {
            $entry = $this->entryRepository->findById($projectId, $entryId);

            $this->logger?->info('Entry retrieval completed', [
                'project_id' => $projectId->value,
                'entry_id' => $entryId->value,
                'found' => $entry !== null,
            ]);

            return $entry;

        } catch (\Throwable $e) {
            $this->logger?->error('Failed to retrieve entry', [
                'project_id' => $projectId->value,
                'entry_id' => $entryId->value,
                'error' => $e->getMessage(),
            ]);

            throw new DraflingException(
                "Failed to retrieve entry: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    #[\Override]
    public function getEntries(ProjectId $projectId, array $filters = []): array
    {
        $this->logger?->info('Retrieving entries', [
            'project_id' => $projectId->value,
            'filters' => $filters,
        ]);

        // Verify project exists
        if (!$this->projectRepository->exists($projectId)) {
            $this->logger?->warning('Attempted to get entries for non-existent project', [
                'project_id' => $projectId->value,
            ]);
            return [];
        }

        try {
            $entries = $this->entryRepository->findByProject($projectId, $filters);

            $this->logger?->info('Entries retrieved successfully', [
                'project_id' => $projectId->value,
                'count' => \count($entries),
                'filters_applied' => !empty($filters),
            ]);

            return $entries;

        } catch (\Throwable $e) {
            $this->logger?->error('Failed to retrieve entries', [
                'project_id' => $projectId->value,
                'filters' => $filters,
                'error' => $e->getMessage(),
            ]);

            throw new DraflingException(
                "Failed to retrieve entries: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    #[\Override]
    public function deleteEntry(ProjectId $projectId, EntryId $entryId): bool
    {
        $this->logger?->info('Deleting entry', [
            'project_id' => $projectId->value,
            'entry_id' => $entryId->value,
        ]);

        // Verify entry exists
        if (!$this->entryRepository->exists($projectId, $entryId)) {
            $this->logger?->warning('Attempted to delete non-existent entry', [
                'project_id' => $projectId->value,
                'entry_id' => $entryId->value,
            ]);
            return false;
        }

        try {
            // Use storage driver to delete the entry
            $deleted = $this->storageDriver->deleteEntry($projectId, $entryId);

            if ($deleted) {
                // Remove from repository
                $this->entryRepository->delete($projectId, $entryId);

                $this->logger?->info('Entry deleted successfully', [
                    'project_id' => $projectId->value,
                    'entry_id' => $entryId->value,
                ]);
            } else {
                $this->logger?->warning('Storage driver failed to delete entry', [
                    'project_id' => $projectId->value,
                    'entry_id' => $entryId->value,
                ]);
            }

            return $deleted;

        } catch (\Throwable $e) {
            $this->logger?->error('Failed to delete entry', [
                'project_id' => $projectId->value,
                'entry_id' => $entryId->value,
                'error' => $e->getMessage(),
            ]);

            throw new DraflingException(
                "Failed to delete entry: {$e->getMessage()}",
                previous: $e,
            );
        }
    }
}
