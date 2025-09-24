<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\Service;

use Butschster\ContextGenerator\Research\Domain\Model\Entry;
use Butschster\ContextGenerator\Research\Domain\ValueObject\EntryId;
use Butschster\ContextGenerator\Research\Domain\ValueObject\ResearchId;
use Butschster\ContextGenerator\Research\Domain\ValueObject\TemplateKey;
use Butschster\ContextGenerator\Research\Exception\ResearchException;
use Butschster\ContextGenerator\Research\Exception\EntryNotFoundException;
use Butschster\ContextGenerator\Research\Exception\ResearchNotFoundException;
use Butschster\ContextGenerator\Research\Exception\TemplateNotFoundException;
use Butschster\ContextGenerator\Research\MCP\DTO\EntryCreateRequest;
use Butschster\ContextGenerator\Research\MCP\DTO\EntryUpdateRequest;
use Butschster\ContextGenerator\Research\Repository\EntryRepositoryInterface;
use Butschster\ContextGenerator\Research\Repository\ResearchRepositoryInterface;
use Butschster\ContextGenerator\Research\Storage\StorageDriverInterface;
use Psr\Log\LoggerInterface;

/**
 * Entry service implementation for entry lifecycle management with template validation
 */
final readonly class EntryService implements EntryServiceInterface
{
    public function __construct(
        private EntryRepositoryInterface $entryRepository,
        private ResearchRepositoryInterface $researches,
        private TemplateServiceInterface $templateService,
        private StorageDriverInterface $storageDriver,
        private ?LoggerInterface $logger = null,
    ) {}

    #[\Override]
    public function createEntry(ResearchId $researchId, EntryCreateRequest $request): Entry
    {
        $this->logger?->info('Creating new entry', [
            'research_id' => $researchId->value,
            'category' => $request->category,
            'entry_type' => $request->entryType,
        ]);

        // Verify research exists
        $research = $this->researches->findById($researchId);
        if ($research === null) {
            $error = "Research '{$researchId->value}' not found";
            $this->logger?->error($error, [
                'research_id' => $researchId->value,
            ]);
            throw new ResearchNotFoundException($error);
        }

        // Get and validate template
        $templateKey = TemplateKey::fromString($research->template);
        $template = $this->templateService->getTemplate($templateKey);
        if ($template === null) {
            $error = "Template '{$research->template}' not found";
            $this->logger?->error($error, [
                'research_id' => $researchId->value,
                'template' => $research->template,
            ]);
            throw new TemplateNotFoundException($error);
        }

        // Resolve display names to internal keys
        $resolvedCategory = $this->templateService->resolveCategoryKey($template, $request->category);
        if ($resolvedCategory === null) {
            $error = "Category '{$request->category}' not found in template '{$research->template}'";
            $this->logger?->error($error, [
                'research_id' => $researchId->value,
                'category' => $request->category,
                'template' => $research->template,
            ]);
            throw new ResearchException($error);
        }

        $resolvedEntryType = $this->templateService->resolveEntryTypeKey($template, $request->entryType);
        if ($resolvedEntryType === null) {
            $error = "Entry type '{$request->entryType}' not found in template '{$research->template}'";
            $this->logger?->error($error, [
                'research_id' => $researchId->value,
                'entry_type' => $request->entryType,
                'template' => $research->template,
            ]);
            throw new ResearchException($error);
        }

        // Validate entry type is allowed in category
        if (!$template->validateEntryInCategory($resolvedCategory, $resolvedEntryType)) {
            $error = "Entry type '{$request->entryType}' is not allowed in category '{$request->category}'";
            $this->logger?->error($error, [
                'research_id' => $researchId->value,
                'category' => $request->category,
                'entry_type' => $request->entryType,
            ]);
            throw new ResearchException($error);
        }

        // Resolve status if provided, otherwise use entry type default
        if ($request->status !== null) {
            $resolvedStatus = $this->templateService->resolveStatusValue($template, $resolvedEntryType, $request->status);
            if ($resolvedStatus === null) {
                $error = "Status '{$request->status}' not found for entry type '{$request->entryType}'";
                $this->logger?->error($error, [
                    'research_id' => $researchId->value,
                    'status' => $request->status,
                    'entry_type' => $request->entryType,
                ]);
                throw new ResearchException($error);
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
            $entry = $this->storageDriver->createEntry($researchId, $resolvedRequest);

            // Save entry to repository
            $this->entryRepository->save($researchId, $entry);

            $this->logger?->info('Entry created successfully', [
                'research_id' => $researchId->value,
                'entry_id' => $entry->entryId,
                'title' => $entry->title,
                'category' => $entry->category,
                'entry_type' => $entry->entryType,
            ]);

            return $entry;

        } catch (\Throwable $e) {
            $this->logger?->error('Failed to create entry', [
                'research_id' => $researchId->value,
                'error' => $e->getMessage(),
            ]);

            throw new ResearchException(
                "Failed to create entry: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    #[\Override]
    public function updateEntry(ResearchId $researchId, EntryId $entryId, EntryUpdateRequest $request): Entry
    {
        $this->logger?->info('Updating entry', [
            'research_id' => $researchId->value,
            'entry_id' => $entryId->value,
            'has_title' => $request->title !== null,
            'has_content' => $request->content !== null,
            'has_status' => $request->status !== null,
        ]);

        // Verify research exists
        $research = $this->researches->findById($researchId);
        if ($research === null) {
            $error = "Research '{$researchId->value}' not found";
            $this->logger?->error($error, [
                'research_id' => $researchId->value,
            ]);
            throw new ResearchNotFoundException($error);
        }

        // Verify entry exists
        $existingEntry = $this->entryRepository->findById($researchId, $entryId);
        if ($existingEntry === null) {
            $error = "Entry '{$entryId->value}' not found in research '{$researchId->value}'";
            $this->logger?->error($error, [
                'research_id' => $researchId->value,
                'entry_id' => $entryId->value,
            ]);
            throw new EntryNotFoundException($error);
        }

        // Resolve status if provided
        $resolvedStatus = $request->status;
        if ($request->status !== null) {
            $templateKey = TemplateKey::fromString($research->template);
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
                        'research_id' => $researchId->value,
                        'entry_id' => $entryId->value,
                        'status' => $request->status,
                        'entry_type' => $existingEntry->entryType,
                    ]);
                    throw new ResearchException($error);
                }

                $resolvedStatus = $resolvedStatusValue;
            }
        }

        try {
            // Create request with resolved status
            $resolvedRequest = $request->withResolvedStatus($resolvedStatus);

            // Use storage driver to update the entry
            $updatedEntry = $this->storageDriver->updateEntry($researchId, $entryId, $resolvedRequest);

            // Save updated entry to repository
            $this->entryRepository->save($researchId, $updatedEntry);

            $this->logger?->info('Entry updated successfully', [
                'research_id' => $researchId->value,
                'entry_id' => $entryId->value,
                'title' => $updatedEntry->title,
            ]);

            return $updatedEntry;

        } catch (\Throwable $e) {
            $this->logger?->error('Failed to update entry', [
                'research_id' => $researchId->value,
                'entry_id' => $entryId->value,
                'error' => $e->getMessage(),
            ]);

            throw new ResearchException(
                "Failed to update entry: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    #[\Override]
    public function entryExists(ResearchId $researchId, EntryId $entryId): bool
    {
        $exists = $this->entryRepository->exists($researchId, $entryId);

        $this->logger?->debug('Checking entry existence', [
            'research_id' => $researchId->value,
            'entry_id' => $entryId->value,
            'exists' => $exists,
        ]);

        return $exists;
    }

    #[\Override]
    public function getEntry(ResearchId $researchId, EntryId $entryId): ?Entry
    {
        $this->logger?->info('Retrieving single entry', [
            'research_id' => $researchId->value,
            'entry_id' => $entryId->value,
        ]);

        // Verify research exists
        if (!$this->researches->exists($researchId)) {
            $error = "Research '{$researchId->value}' not found";
            $this->logger?->error($error, [
                'research_id' => $researchId->value,
            ]);
            throw new ResearchNotFoundException($error);
        }

        try {
            $entry = $this->entryRepository->findById($researchId, $entryId);

            $this->logger?->info('Entry retrieval completed', [
                'research_id' => $researchId->value,
                'entry_id' => $entryId->value,
                'found' => $entry !== null,
            ]);

            return $entry;

        } catch (\Throwable $e) {
            $this->logger?->error('Failed to retrieve entry', [
                'research_id' => $researchId->value,
                'entry_id' => $entryId->value,
                'error' => $e->getMessage(),
            ]);

            throw new ResearchException(
                "Failed to retrieve entry: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    #[\Override]
    public function findAll(ResearchId $researchId, array $filters = []): array
    {
        $this->logger?->info('Retrieving entries', [
            'research_id' => $researchId->value,
            'filters' => $filters,
        ]);

        // Verify research exists
        if (!$this->researches->exists($researchId)) {
            $this->logger?->warning('Attempted to get entries for non-existent research', [
                'research_id' => $researchId->value,
            ]);
            return [];
        }

        try {
            $entries = $this->entryRepository->findByResearch($researchId, $filters);

            $this->logger?->info('Entries retrieved successfully', [
                'research_id' => $researchId->value,
                'count' => \count($entries),
                'filters_applied' => !empty($filters),
            ]);

            return $entries;

        } catch (\Throwable $e) {
            $this->logger?->error('Failed to retrieve entries', [
                'research_id' => $researchId->value,
                'filters' => $filters,
                'error' => $e->getMessage(),
            ]);

            throw new ResearchException(
                "Failed to retrieve entries: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    #[\Override]
    public function deleteEntry(ResearchId $researchId, EntryId $entryId): bool
    {
        $this->logger?->info('Deleting entry', [
            'research_id' => $researchId->value,
            'entry_id' => $entryId->value,
        ]);

        // Verify entry exists
        if (!$this->entryRepository->exists($researchId, $entryId)) {
            $this->logger?->warning('Attempted to delete non-existent entry', [
                'research_id' => $researchId->value,
                'entry_id' => $entryId->value,
            ]);
            return false;
        }

        try {
            // Use storage driver to delete the entry
            $deleted = $this->storageDriver->deleteEntry($researchId, $entryId);

            if ($deleted) {
                // Remove from repository
                $this->entryRepository->delete($researchId, $entryId);

                $this->logger?->info('Entry deleted successfully', [
                    'research_id' => $researchId->value,
                    'entry_id' => $entryId->value,
                ]);
            } else {
                $this->logger?->warning('Storage driver failed to delete entry', [
                    'research_id' => $researchId->value,
                    'entry_id' => $entryId->value,
                ]);
            }

            return $deleted;

        } catch (\Throwable $e) {
            $this->logger?->error('Failed to delete entry', [
                'research_id' => $researchId->value,
                'entry_id' => $entryId->value,
                'error' => $e->getMessage(),
            ]);

            throw new ResearchException(
                "Failed to delete entry: {$e->getMessage()}",
                previous: $e,
            );
        }
    }
}
