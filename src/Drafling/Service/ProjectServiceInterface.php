<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Service;

use Butschster\ContextGenerator\Drafling\Domain\Model\Project;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\ProjectId;
use Butschster\ContextGenerator\Drafling\MCP\DTO\ProjectCreateRequest;
use Butschster\ContextGenerator\Drafling\MCP\DTO\ProjectUpdateRequest;

/**
 * Service interface for project operations
 */
interface ProjectServiceInterface
{
    /**
     * Create a new project from template
     *
     * @param ProjectCreateRequest $request
     * @return Project
     * @throws \Butschster\ContextGenerator\Drafling\Exception\TemplateNotFoundException
     * @throws \Butschster\ContextGenerator\Drafling\Exception\DraflingException
     */
    public function createProject(ProjectCreateRequest $request): Project;

    /**
     * Update an existing project
     *
     * @param ProjectId $projectId
     * @param ProjectUpdateRequest $request
     * @return Project
     * @throws \Butschster\ContextGenerator\Drafling\Exception\ProjectNotFoundException
     * @throws \Butschster\ContextGenerator\Drafling\Exception\DraflingException
     */
    public function updateProject(ProjectId $projectId, ProjectUpdateRequest $request): Project;

    /**
     * Check if a project exists
     */
    public function projectExists(ProjectId $projectId): bool;

    /**
     * Get a single project by ID
     *
     * @param ProjectId $projectId
     * @return Project|null
     */
    public function getProject(ProjectId $projectId): ?Project;

    /**
     * List projects with optional filtering
     *
     * @param array $filters
     * @return Project[]
     */
    public function listProjects(array $filters = []): array;

    /**
     * Delete a project
     *
     * @param ProjectId $projectId
     * @return bool
     */
    public function deleteProject(ProjectId $projectId): bool;
}
