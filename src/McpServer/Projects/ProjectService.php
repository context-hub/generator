<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Projects;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\McpServer\Projects\DTO\CurrentProjectDTO;
use Butschster\ContextGenerator\McpServer\Projects\DTO\ProjectDTO;
use Butschster\ContextGenerator\McpServer\Projects\DTO\ProjectStateDTO;
use Butschster\ContextGenerator\McpServer\Projects\Repository\ProjectStateRepository;
use Psr\Log\LoggerInterface;
use Spiral\Files\FilesInterface;

/**
 * Service for managing project state
 */
#[LoggerPrefix(prefix: 'projects')]
final class ProjectService
{
    /**
     * Project state cache
     */
    private ?ProjectStateDTO $state = null;

    public function __construct(
        private readonly FilesInterface $files,
        private readonly LoggerInterface $logger,
        private readonly ProjectStateRepository $repository,
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

    /**
     * Switch to an existing project without modifying its configuration
     * Used for switching between projects without overriding their states
     *
     * @param string $projectPath Absolute path to the project directory
     * @return bool True if the project was found and switched to, false otherwise
     */
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

    /**
     * Get a list of all projects
     *
     * @return array<string, ProjectDTO>
     */
    public function getProjects(): array
    {
        return $this->getState()->projects;
    }

    /**
     * Get all project aliases
     *
     * @return array<string, string> Alias to path mapping
     */
    public function getAliases(): array
    {
        return $this->getState()->aliases;
    }

    /**
     * Get aliases for a specific project path
     *
     * @return string[]
     */
    public function getAliasesForPath(string $projectPath): array
    {
        return $this->getState()->getAliasesForPath($projectPath);
    }

    /**
     * Resolve a path or alias to the actual project path
     */
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
