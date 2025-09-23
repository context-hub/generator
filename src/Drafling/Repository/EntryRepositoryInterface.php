<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Repository;

use Butschster\ContextGenerator\Drafling\Domain\Model\Entry;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\EntryId;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\ProjectId;

/**
 * Repository interface for managing entries
 */
interface EntryRepositoryInterface
{
    /**
     * Find all entries for a project with optional filtering
     *
     * @param array $filters Associative array of filters (category, status, entry_type, tags, etc.)
     * @return Entry[]
     */
    public function findByProject(ProjectId $projectId, array $filters = []): array;

    /**
     * Find entry by project and entry ID
     */
    public function findById(ProjectId $projectId, EntryId $entryId): ?Entry;

    /**
     * Save entry to storage
     */
    public function save(ProjectId $projectId, Entry $entry): void;

    /**
     * Delete entry from storage
     */
    public function delete(ProjectId $projectId, EntryId $entryId): bool;

    /**
     * Check if entry exists
     */
    public function exists(ProjectId $projectId, EntryId $entryId): bool;
}
