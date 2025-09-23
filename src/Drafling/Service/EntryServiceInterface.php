<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Service;

use Butschster\ContextGenerator\Drafling\Domain\Model\Entry;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\EntryId;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\ProjectId;
use Butschster\ContextGenerator\Drafling\MCP\DTO\EntryCreateRequest;
use Butschster\ContextGenerator\Drafling\MCP\DTO\EntryUpdateRequest;

/**
 * Entry service contract for managing entry operations
 */
interface EntryServiceInterface
{
    /**
     * Get entries for a project with optional filtering
     *
     * @param ProjectId $projectId Project identifier
     * @param array $filters Optional filters (category, status, tags, etc.)
     * @return Entry[]
     */
    public function getEntries(ProjectId $projectId, array $filters = []): array;

    /**
     * Get specific entry by ID
     */
    public function getEntry(ProjectId $projectId, EntryId $entryId): ?Entry;

    /**
     * Create new entry
     */
    public function createEntry(ProjectId $projectId, EntryCreateRequest $request): Entry;

    /**
     * Update existing entry
     */
    public function updateEntry(ProjectId $projectId, EntryId $entryId, EntryUpdateRequest $request): Entry;

    /**
     * Delete entry
     */
    public function deleteEntry(ProjectId $projectId, EntryId $entryId): bool;

    /**
     * Check if entry exists
     */
    public function entryExists(ProjectId $projectId, EntryId $entryId): bool;
}
