<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Repository;

use Butschster\ContextGenerator\Drafling\Domain\Model\Entry;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\EntryId;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\ProjectId;

/**
 * Entry repository contract for persisting entry data
 */
interface EntryRepositoryInterface
{
    /**
     * Find entries for a project with optional filters
     * 
     * @param ProjectId $projectId Project identifier
     * @param array $filters Optional filters (category, status, tags, etc.)
     * @return Entry[]
     */
    public function findByProject(ProjectId $projectId, array $filters = []): array;

    /**
     * Find specific entry
     */
    public function findById(ProjectId $projectId, EntryId $entryId): ?Entry;

    /**
     * Save entry (create or update)
     */
    public function save(ProjectId $projectId, Entry $entry): void;

    /**
     * Delete entry
     */
    public function delete(ProjectId $projectId, EntryId $entryId): bool;

    /**
     * Check if entry exists
     */
    public function exists(ProjectId $projectId, EntryId $entryId): bool;
}
