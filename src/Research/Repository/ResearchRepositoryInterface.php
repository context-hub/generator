<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\Repository;

use Butschster\ContextGenerator\Research\Domain\Model\Research;
use Butschster\ContextGenerator\Research\Domain\ValueObject\ResearchId;

/**
 * Repository interface for managing researches
 */
interface ResearchRepositoryInterface
{
    /**
     * Find all researches with optional filtering
     *
     * @param array $filters Associative array of filters (status, template, tags, name_contains, etc.)
     * @return Research[]
     */
    public function findAll(array $filters = []): array;

    /**
     * Find research by ID
     */
    public function findById(ResearchId $id): ?Research;

    /**
     * Save research to storage
     */
    public function save(Research $research): void;

    /**
     * Delete research from storage
     */
    public function delete(ResearchId $id): bool;

    /**
     * Check if research exists
     */
    public function exists(ResearchId $id): bool;
}
