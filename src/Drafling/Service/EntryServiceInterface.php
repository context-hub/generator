<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Service;

use Butschster\ContextGenerator\Drafling\Domain\Model\Entry;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\EntryId;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\ProjectId;
use Butschster\ContextGenerator\Drafling\MCP\DTO\EntryCreateRequest;
use Butschster\ContextGenerator\Drafling\MCP\DTO\EntryUpdateRequest;

/**
 * Service interface for entry operations
 */
interface EntryServiceInterface
{
    /**
     * Create a new entry in the specified project
     *
     * @param ProjectId $projectId
     * @param EntryCreateRequest $request
     * @return Entry
     * @throws \Butschster\ContextGenerator\Drafling\Exception\ProjectNotFoundException
     * @throws \Butschster\ContextGenerator\Drafling\Exception\TemplateNotFoundException
     * @throws \Butschster\ContextGenerator\Drafling\Exception\DraflingException
     */
    public function createEntry(ProjectId $projectId, EntryCreateRequest $request): Entry;

    /**
     * Update an existing entry
     *
     * @param ProjectId $projectId
     * @param EntryId $entryId
     * @param EntryUpdateRequest $request
     * @return Entry
     * @throws \Butschster\ContextGenerator\Drafling\Exception\ProjectNotFoundException
     * @throws \Butschster\ContextGenerator\Drafling\Exception\EntryNotFoundException
     * @throws \Butschster\ContextGenerator\Drafling\Exception\DraflingException
     */
    public function updateEntry(ProjectId $projectId, EntryId $entryId, EntryUpdateRequest $request): Entry;

    /**
     * Check if an entry exists
     */
    public function entryExists(ProjectId $projectId, EntryId $entryId): bool;

    /**
     * Get a specific entry by ID
     *
     * @param ProjectId $projectId
     * @param EntryId $entryId
     * @return Entry|null
     * @throws \Butschster\ContextGenerator\Drafling\Exception\ProjectNotFoundException
     * @throws \Butschster\ContextGenerator\Drafling\Exception\DraflingException
     */
    public function getEntry(ProjectId $projectId, EntryId $entryId): ?Entry;

    /**
     * Get entries for a project with optional filtering
     *
     * @param ProjectId $projectId
     * @param array $filters
     * @return Entry[]
     */
    public function getEntries(ProjectId $projectId, array $filters = []): array;

    /**
     * Delete an entry
     *
     * @param ProjectId $projectId
     * @param EntryId $entryId
     * @return bool
     */
    public function deleteEntry(ProjectId $projectId, EntryId $entryId): bool;
}
