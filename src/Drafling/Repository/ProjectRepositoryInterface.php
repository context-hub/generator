<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Repository;

use Butschster\ContextGenerator\Drafling\Domain\Model\Project;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\ProjectId;

/**
 * Repository interface for managing projects
 */
interface ProjectRepositoryInterface
{
    /**
     * Find all projects with optional filtering
     *
     * @param array $filters Associative array of filters (status, template, tags, name_contains, etc.)
     * @return Project[]
     */
    public function findAll(array $filters = []): array;

    /**
     * Find project by ID
     */
    public function findById(ProjectId $id): ?Project;

    /**
     * Save project to storage
     */
    public function save(Project $project): void;

    /**
     * Delete project from storage
     */
    public function delete(ProjectId $id): bool;

    /**
     * Check if project exists
     */
    public function exists(ProjectId $id): bool;
}
