<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\Repository;

use Butschster\ContextGenerator\Research\Domain\Model\Entry;
use Butschster\ContextGenerator\Research\Domain\ValueObject\EntryId;
use Butschster\ContextGenerator\Research\Domain\ValueObject\ResearchId;

/**
 * Repository interface for managing entries
 */
interface EntryRepositoryInterface
{
    /**
     * Find all entries for a research with optional filtering
     *
     * @param array $filters Associative array of filters (category, status, entry_type, tags, etc.)
     * @return Entry[]
     */
    public function findByResearch(ResearchId $researchId, array $filters = []): array;

    /**
     * Find entry by research and entry ID
     */
    public function findById(ResearchId $researchId, EntryId $entryId): ?Entry;

    /**
     * Save entry to storage
     */
    public function save(ResearchId $researchId, Entry $entry): void;

    /**
     * Delete entry from storage
     */
    public function delete(ResearchId $researchId, EntryId $entryId): bool;

    /**
     * Check if entry exists
     */
    public function exists(ResearchId $researchId, EntryId $entryId): bool;
}
