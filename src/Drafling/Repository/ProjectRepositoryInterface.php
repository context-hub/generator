<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Repository;

use Butschster\ContextGenerator\Drafling\Domain\Model\Project;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\ProjectId;

/**
 * Project repository contract for persisting project data
 */
interface ProjectRepositoryInterface
{
    /**
     * Find all projects with optional filters
     * 
     * @param array $filters Optional filters (status, template, tags, etc.)
     * @return Project[]
     */
    public function findAll(array $filters = []): array;

    /**
     * Find project by ID
     */
    public function findById(ProjectId $id): ?Project;

    /**
     * Save project (create or update)
     */
    public function save(Project $project): void;

    /**
     * Delete project
     */
    public function delete(ProjectId $id): bool;

    /**
     * Check if project exists
     */
    public function exists(ProjectId $id): bool;
}
