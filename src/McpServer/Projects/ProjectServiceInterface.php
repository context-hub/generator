<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Projects;

use Butschster\ContextGenerator\McpServer\Projects\DTO\CurrentProjectDTO;
use Butschster\ContextGenerator\McpServer\Projects\DTO\ProjectDTO;

interface ProjectServiceInterface
{
    public function getCurrentProject(): ?CurrentProjectDTO;

    public function setCurrentProject(
        string $projectPath,
        ?string $alias = null,
        ?string $configFile = null,
        ?string $envFile = null,
    ): void;

    /**
     * Switch to an existing project without modifying its configuration
     * Used for switching between projects without overriding their states
     *
     * @param string $projectPath Absolute path to the project directory
     * @return bool True if the project was found and switched to, false otherwise
     */
    public function switchToProject(string $projectPath): bool;

    public function addProject(
        string $projectPath,
        ?string $alias = null,
        ?string $configFile = null,
        ?string $envFile = null,
    ): void;

    /**
     * Get a list of all projects
     *
     * @return array<string, ProjectDTO>
     */
    public function getProjects(): array;

    /**
     * Get all project aliases
     *
     * @return array<string, string> Alias to path mapping
     */
    public function getAliases(): array;

    /**
     * Get aliases for a specific project path
     *
     * @return string[]
     */
    public function getAliasesForPath(string $projectPath): array;

    /**
     * Resolve a path or alias to the actual project path
     */
    public function resolvePathOrAlias(string $pathOrAlias): string;
}
