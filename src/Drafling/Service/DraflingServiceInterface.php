<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Service;

use Butschster\ContextGenerator\Drafling\Domain\Model\Entry;
use Butschster\ContextGenerator\Drafling\Domain\Model\Project;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\ProjectId;

/**
 * Main Drafling service contract for orchestrating operations
 */
interface DraflingServiceInterface
{
    /**
     * Get project content formatted for viewing
     *
     * @param ProjectId $projectId Project identifier
     * @param array $options Formatting options (categories, format, include_metadata)
     */
    public function getProjectContent(ProjectId $projectId, array $options = []): array;

    /**
     * Get project structure information
     */
    public function getProjectStructure(ProjectId $projectId): array;

    /**
     * Get project history/timeline
     */
    public function getProjectHistory(ProjectId $projectId): array;

    /**
     * Validate project against its template
     *
     * @return array Array of validation errors (empty if valid)
     */
    public function validateProject(Project $project): array;

    /**
     * Validate entry against project template
     *
     * @return array Array of validation errors (empty if valid)
     */
    public function validateEntry(Entry $entry, Project $project): array;
}
