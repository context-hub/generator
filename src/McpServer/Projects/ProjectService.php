<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Projects;

use Butschster\ContextGenerator\McpServer\Projects\DTO\CurrentProjectDTO;
use Butschster\ContextGenerator\McpServer\Projects\DTO\ProjectDTO;
use Butschster\ContextGenerator\McpServer\Projects\DTO\ProjectStateDTO;
use Butschster\ContextGenerator\McpServer\Projects\Repository\ProjectStateRepositoryInterface;
use Spiral\Files\FilesInterface;

final class ProjectService implements ProjectServiceInterface
{
    /** Project state cache  */
    private ?ProjectStateDTO $state = null;

    public function __construct(
        private readonly FilesInterface $files,
        private readonly ProjectStateRepositoryInterface $repository,
    ) {}

    public function getCurrentProject(): ?CurrentProjectDTO
    {
        $state = $this->getState();
        $currentProject = $state->currentProject;

        if ($currentProject === null) {
            return null;
        }

        // Verify the project path still exists
        if (!$this->files->exists($currentProject->path)) {
            return null;
        }

        return $currentProject;
    }

    public function setCurrentProject(
        string $projectPath,
        ?string $alias = null,
        ?string $configFile = null,
        ?string $envFile = null,
    ): void {
        $state = $this->getState();

        // Set current project
        $state->currentProject = new CurrentProjectDTO(
            path: $projectPath,
            configFile: $configFile,
            envFile: $envFile,
        );

        // Register the alias if provided
        if ($alias !== null) {
            $state->aliases[$alias] = $projectPath;
        }

        // Add to projects list if not already there
        if (!isset($state->projects[$projectPath])) {
            $state->projects[$projectPath] = new ProjectDTO(
                path: $projectPath,
                addedAt: \date('Y-m-d H:i:s'),
                configFile: $configFile,
                envFile: $envFile,
            );
        } else {
            // Update existing project if config file or env file provided
            $existingProject = $state->projects[$projectPath];
            $needsUpdate = ($configFile !== null && $existingProject->configFile !== $configFile) ||
                ($envFile !== null && $existingProject->envFile !== $envFile);

            if ($needsUpdate) {
                $state->projects[$projectPath] = new ProjectDTO(
                    path: $projectPath,
                    addedAt: $existingProject->addedAt,
                    configFile: $configFile ?? $existingProject->configFile,
                    envFile: $envFile ?? $existingProject->envFile,
                );
            }
        }

        $this->saveState($state);
    }

    public function switchToProject(string $projectPath): bool
    {
        $state = $this->getState();

        // Check if project exists
        if (!isset($state->projects[$projectPath])) {
            return false;
        }

        // Get existing project details
        $projectInfo = $state->projects[$projectPath];

        // Switch to the project without changing its configuration
        $state->currentProject = new CurrentProjectDTO(
            path: $projectPath,
            configFile: $projectInfo->configFile,
            envFile: $projectInfo->envFile,
        );

        $this->saveState($state);
        return true;
    }

    public function addProject(
        string $projectPath,
        ?string $alias = null,
        ?string $configFile = null,
        ?string $envFile = null,
    ): void {
        $state = $this->getState();

        // Register the alias if provided
        if ($alias !== null) {
            $state->aliases[$alias] = $projectPath;
        }

        // Add to projects list if not already there
        if (!isset($state->projects[$projectPath])) {
            $state->projects[$projectPath] = new ProjectDTO(
                path: $projectPath,
                addedAt: \date('Y-m-d H:i:s'),
                configFile: $configFile,
                envFile: $envFile,
            );
        } else {
            // Update existing project
            $existingProject = $state->projects[$projectPath];
            $state->projects[$projectPath] = new ProjectDTO(
                path: $projectPath,
                addedAt: $existingProject->addedAt,
                configFile: $configFile ?? $existingProject->configFile,
                envFile: $envFile ?? $existingProject->envFile,
            );
        }

        // if current project is set and matches the new project path, update it
        if ($this->getCurrentProject()?->path === $projectPath) {
            $this->switchToProject($projectPath);
        }

        $this->saveState($state);
    }

    public function getProjects(): array
    {
        return $this->getState()->projects;
    }

    public function getAliases(): array
    {
        return $this->getState()->aliases;
    }

    public function getAliasesForPath(string $projectPath): array
    {
        return $this->getState()->getAliasesForPath($projectPath);
    }

    public function resolvePathOrAlias(string $pathOrAlias): string
    {
        return $this->getState()->resolvePathOrAlias($pathOrAlias);
    }

    /**
     * Get the current project state, loading from storage if necessary
     */
    private function getState(): ProjectStateDTO
    {
        if ($this->state === null) {
            $this->state = $this->repository->load();
        }

        return $this->state;
    }

    /**
     * Save the project state to disk
     */
    private function saveState(ProjectStateDTO $state): void
    {
        $this->state = $state;
        $this->repository->save($state);
    }
}
