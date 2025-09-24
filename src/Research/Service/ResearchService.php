<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\Service;

use Butschster\ContextGenerator\Research\Domain\Model\Research;
use Butschster\ContextGenerator\Research\Domain\ValueObject\ResearchId;
use Butschster\ContextGenerator\Research\Domain\ValueObject\TemplateKey;
use Butschster\ContextGenerator\Research\Exception\ResearchException;
use Butschster\ContextGenerator\Research\Exception\ResearchNotFoundException;
use Butschster\ContextGenerator\Research\Exception\TemplateNotFoundException;
use Butschster\ContextGenerator\Research\MCP\DTO\ResearchCreateRequest;
use Butschster\ContextGenerator\Research\MCP\DTO\ResearchUpdateRequest;
use Butschster\ContextGenerator\Research\Repository\ResearchRepositoryInterface;
use Butschster\ContextGenerator\Research\Storage\StorageDriverInterface;
use Psr\Log\LoggerInterface;

final readonly class ResearchService implements ResearchServiceInterface
{
    public function __construct(
        private ResearchRepositoryInterface $researches,
        private TemplateServiceInterface $templateService,
        private StorageDriverInterface $storageDriver,
        private ?LoggerInterface $logger = null,
    ) {}

    #[\Override]
    public function create(ResearchCreateRequest $request): Research
    {
        $this->logger?->info('Creating new research', [
            'template_id' => $request->templateId,
            'title' => $request->title,
        ]);

        // Validate template exists
        $templateKey = TemplateKey::fromString($request->templateId);
        if (!$this->templateService->templateExists($templateKey)) {
            $error = "Template '{$request->templateId}' not found";
            $this->logger?->error($error, [
                'template_id' => $request->templateId,
            ]);
            throw new TemplateNotFoundException($error);
        }

        try {
            // Use storage driver to create the research
            $research = $this->storageDriver->createResearch($request);

            // Save research to repository
            $this->researches->save($research);

            $this->logger?->info('Research created successfully', [
                'research_id' => $research->id,
                'template' => $research->template,
                'name' => $research->name,
            ]);

            return $research;

        } catch (\Throwable $e) {
            $this->logger?->error('Failed to create research', [
                'template_id' => $request->templateId,
                'title' => $request->title,
                'error' => $e->getMessage(),
            ]);

            throw new ResearchException(
                "Failed to create research: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    #[\Override]
    public function update(ResearchId $researchId, ResearchUpdateRequest $request): Research
    {
        $this->logger?->info('Updating research', [
            'research_id' => $researchId->value,
            'updates' => [
                'title' => $request->title !== null,
                'description' => $request->description !== null,
                'status' => $request->status !== null,
                'tags' => $request->tags !== null,
                'entry_dirs' => $request->entryDirs !== null,
                'memory' => $request->memory !== null,
            ],
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
            // Use storage driver to update the research
            $updatedResearch = $this->storageDriver->updateResearch($researchId, $request);

            // Save updated research to repository
            $this->researches->save($updatedResearch);

            $this->logger?->info('Research updated successfully', [
                'research_id' => $researchId->value,
                'name' => $updatedResearch->name,
                'status' => $updatedResearch->status,
            ]);

            return $updatedResearch;

        } catch (\Throwable $e) {
            $this->logger?->error('Failed to update research', [
                'research_id' => $researchId->value,
                'error' => $e->getMessage(),
            ]);

            throw new ResearchException(
                "Failed to update research: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    #[\Override]
    public function exists(ResearchId $researchId): bool
    {
        $exists = $this->researches->exists($researchId);

        $this->logger?->debug('Checking research existence', [
            'research_id' => $researchId->value,
            'exists' => $exists,
        ]);

        return $exists;
    }

    #[\Override]
    public function get(ResearchId $researchId): ?Research
    {
        $this->logger?->debug('Retrieving research', [
            'research_id' => $researchId->value,
        ]);

        $research = $this->researches->findById($researchId);

        if ($research === null) {
            $this->logger?->warning('Research not found', [
                'research_id' => $researchId->value,
            ]);
        } else {
            $this->logger?->debug('Research retrieved successfully', [
                'research_id' => $research->id,
                'name' => $research->name,
                'template' => $research->template,
            ]);
        }

        return $research;
    }

    #[\Override]
    public function findAll(array $filters = []): array
    {
        $this->logger?->info('Listing researches', [
            'filters' => $filters,
        ]);

        try {
            $researches = $this->researches->findAll($filters);

            $this->logger?->info('Researches retrieved successfully', [
                'count' => \count($researches),
                'filters_applied' => !empty($filters),
            ]);

            return $researches;

        } catch (\Throwable $e) {
            $this->logger?->error('Failed to list researches', [
                'filters' => $filters,
                'error' => $e->getMessage(),
            ]);

            throw new ResearchException(
                "Failed to list researches: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    #[\Override]
    public function delete(ResearchId $researchId): bool
    {
        $this->logger?->info('Deleting research', [
            'research_id' => $researchId->value,
        ]);

        // Verify research exists
        if (!$this->researches->exists($researchId)) {
            $this->logger?->warning('Attempted to delete non-existent research', [
                'research_id' => $researchId->value,
            ]);
            return false;
        }

        try {
            // Use storage driver to delete the research and its entries
            $deleted = $this->storageDriver->deleteResearch($researchId);

            if ($deleted) {
                // Remove from repository
                $this->researches->delete($researchId);

                $this->logger?->info('Research deleted successfully', [
                    'research_id' => $researchId->value,
                ]);
            } else {
                $this->logger?->warning('Storage driver failed to delete research', [
                    'research_id' => $researchId->value,
                ]);
            }

            return $deleted;

        } catch (\Throwable $e) {
            $this->logger?->error('Failed to delete research', [
                'research_id' => $researchId->value,
                'error' => $e->getMessage(),
            ]);

            throw new ResearchException(
                "Failed to delete research: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    #[\Override]
    public function addMemory(ResearchId $researchId, string $memory): Research
    {
        $this->logger?->info('Adding memory to research', [
            'research_id' => $researchId->value,
            'memory_length' => \strlen($memory),
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

        try {
            // Create updated research with added memory
            $updatedResearch = $research->withAddedMemory($memory);

            // Save updated research to repository
            $this->researches->save($updatedResearch);

            $this->logger?->info('Memory added to research successfully', [
                'research_id' => $researchId->value,
                'memory_count' => \count($updatedResearch->memory),
                'name' => $updatedResearch->name,
            ]);

            return $updatedResearch;

        } catch (\Throwable $e) {
            $this->logger?->error('Failed to add memory to research', [
                'research_id' => $researchId->value,
                'error' => $e->getMessage(),
            ]);

            throw new ResearchException(
                "Failed to add memory to research: {$e->getMessage()}",
                previous: $e,
            );
        }
    }
}
