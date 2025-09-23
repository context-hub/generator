<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Service;

use Butschster\ContextGenerator\Drafling\Domain\Model\Project;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\ProjectId;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\TemplateKey;
use Butschster\ContextGenerator\Drafling\Exception\DraflingException;
use Butschster\ContextGenerator\Drafling\Exception\ProjectNotFoundException;
use Butschster\ContextGenerator\Drafling\Exception\TemplateNotFoundException;
use Butschster\ContextGenerator\Drafling\MCP\DTO\ProjectCreateRequest;
use Butschster\ContextGenerator\Drafling\MCP\DTO\ProjectUpdateRequest;
use Butschster\ContextGenerator\Drafling\Repository\ProjectRepositoryInterface;
use Butschster\ContextGenerator\Drafling\Storage\StorageDriverInterface;
use Psr\Log\LoggerInterface;

/**
 * Project service implementation for project lifecycle management
 */
final readonly class ProjectService implements ProjectServiceInterface
{
    public function __construct(
        private ProjectRepositoryInterface $projectRepository,
        private TemplateServiceInterface $templateService,
        private StorageDriverInterface $storageDriver,
        private ?LoggerInterface $logger = null,
    ) {}

    #[\Override]
    public function createProject(ProjectCreateRequest $request): Project
    {
        $this->logger?->info('Creating new project', [
            'template_id' => $request->templateId,
            'title' => $request->title,
        ]);

        // Validate template exists
        $templateKey = TemplateKey::fromString($request->templateId);
        if (!$this->templateService->templateExists($templateKey)) {
            $error = "Template '{$request->templateId}' not found";
            $this->logger?->error($error, [
                'template_id' => $request->templateId,
            ]);
            throw new TemplateNotFoundException($error);
        }

        try {
            // Use storage driver to create the project
            $project = $this->storageDriver->createProject($request);

            // Save project to repository
            $this->projectRepository->save($project);

            $this->logger?->info('Project created successfully', [
                'project_id' => $project->id,
                'template' => $project->template,
                'name' => $project->name,
            ]);

            return $project;

        } catch (\Throwable $e) {
            $this->logger?->error('Failed to create project', [
                'template_id' => $request->templateId,
                'title' => $request->title,
                'error' => $e->getMessage(),
            ]);

            throw new DraflingException(
                "Failed to create project: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    #[\Override]
    public function updateProject(ProjectId $projectId, ProjectUpdateRequest $request): Project
    {
        $this->logger?->info('Updating project', [
            'project_id' => $projectId->value,
            'updates' => [
                'title' => $request->title !== null,
                'description' => $request->description !== null,
                'status' => $request->status !== null,
                'tags' => $request->tags !== null,
                'entry_dirs' => $request->entryDirs !== null,
            ],
        ]);

        // Verify project exists
        if (!$this->projectRepository->exists($projectId)) {
            $error = "Project '{$projectId->value}' not found";
            $this->logger?->error($error, [
                'project_id' => $projectId->value,
            ]);
            throw new ProjectNotFoundException($error);
        }

        try {
            // Use storage driver to update the project
            $updatedProject = $this->storageDriver->updateProject($projectId, $request);

            // Save updated project to repository
            $this->projectRepository->save($updatedProject);

            $this->logger?->info('Project updated successfully', [
                'project_id' => $projectId->value,
                'name' => $updatedProject->name,
                'status' => $updatedProject->status,
            ]);

            return $updatedProject;

        } catch (\Throwable $e) {
            $this->logger?->error('Failed to update project', [
                'project_id' => $projectId->value,
                'error' => $e->getMessage(),
            ]);

            throw new DraflingException(
                "Failed to update project: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    #[\Override]
    public function projectExists(ProjectId $projectId): bool
    {
        $exists = $this->projectRepository->exists($projectId);

        $this->logger?->debug('Checking project existence', [
            'project_id' => $projectId->value,
            'exists' => $exists,
        ]);

        return $exists;
    }

    #[\Override]
    public function getProject(ProjectId $projectId): ?Project
    {
        $this->logger?->debug('Retrieving project', [
            'project_id' => $projectId->value,
        ]);

        $project = $this->projectRepository->findById($projectId);

        if ($project === null) {
            $this->logger?->warning('Project not found', [
                'project_id' => $projectId->value,
            ]);
        } else {
            $this->logger?->debug('Project retrieved successfully', [
                'project_id' => $project->id,
                'name' => $project->name,
                'template' => $project->template,
            ]);
        }

        return $project;
    }

    #[\Override]
    public function listProjects(array $filters = []): array
    {
        $this->logger?->info('Listing projects', [
            'filters' => $filters,
        ]);

        try {
            $projects = $this->projectRepository->findAll($filters);

            $this->logger?->info('Projects retrieved successfully', [
                'count' => \count($projects),
                'filters_applied' => !empty($filters),
            ]);

            return $projects;

        } catch (\Throwable $e) {
            $this->logger?->error('Failed to list projects', [
                'filters' => $filters,
                'error' => $e->getMessage(),
            ]);

            throw new DraflingException(
                "Failed to list projects: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    #[\Override]
    public function deleteProject(ProjectId $projectId): bool
    {
        $this->logger?->info('Deleting project', [
            'project_id' => $projectId->value,
        ]);

        // Verify project exists
        if (!$this->projectRepository->exists($projectId)) {
            $this->logger?->warning('Attempted to delete non-existent project', [
                'project_id' => $projectId->value,
            ]);
            return false;
        }

        try {
            // Use storage driver to delete the project and its entries
            $deleted = $this->storageDriver->deleteProject($projectId);

            if ($deleted) {
                // Remove from repository
                $this->projectRepository->delete($projectId);

                $this->logger?->info('Project deleted successfully', [
                    'project_id' => $projectId->value,
                ]);
            } else {
                $this->logger?->warning('Storage driver failed to delete project', [
                    'project_id' => $projectId->value,
                ]);
            }

            return $deleted;

        } catch (\Throwable $e) {
            $this->logger?->error('Failed to delete project', [
                'project_id' => $projectId->value,
                'error' => $e->getMessage(),
            ]);

            throw new DraflingException(
                "Failed to delete project: {$e->getMessage()}",
                previous: $e,
            );
        }
    }
}
