<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Service;

use Butschster\ContextGenerator\Drafling\Domain\Model\Project;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\ProjectId;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\TemplateKey;
use Butschster\ContextGenerator\Drafling\MCP\DTO\ProjectCreateRequest;
use Butschster\ContextGenerator\Drafling\MCP\DTO\ProjectUpdateRequest;

/**
 * Project service contract for managing project lifecycle
 */
interface ProjectServiceInterface
{
    /**
     * Get all projects with optional filtering
     *
     * @param array $filters Optional filters (status, tags, template, etc.)
     * @return Project[]
     */
    public function listProjects(array $filters = []): array;

    /**
     * Get project by ID
     */
    public function getProject(ProjectId $id): ?Project;

    /**
     * Create new project
     */
    public function createProject(ProjectCreateRequest $request): Project;

    /**
     * Update existing project
     */
    public function updateProject(ProjectId $id, ProjectUpdateRequest $request): Project;

    /**
     * Delete project
     */
    public function deleteProject(ProjectId $id): bool;

    /**
     * Check if project exists
     */
    public function projectExists(ProjectId $id): bool;

    /**
     * Get project template
     */
    public function getProjectTemplate(ProjectId $id): ?TemplateKey;
}
