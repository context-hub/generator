<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Projects\DTO;

/**
 * Contains the complete project state
 */
final class ProjectStateDTO implements \JsonSerializable
{
    /**
     * @param CurrentProjectDTO|null $currentProject Currently active project
     * @param array<string, ProjectDTO> $projects Collection of all projects indexed by path
     * @param array<string, string> $aliases Project aliases mapping (alias => path)
     */
    public function __construct(
        public ?CurrentProjectDTO $currentProject = null,
        public array $projects = [],
        public array $aliases = [],
    ) {}

    /**
     * Create from array data (typically loaded from storage)
     */
    public static function fromArray(array $data): self
    {
        // Process current project
        $currentProject = CurrentProjectDTO::fromArray($data['current_project'] ?? null);

        // Process projects
        $projects = [];
        foreach ($data['projects'] ?? [] as $path => $projectData) {
            $projects[$path] = ProjectDTO::fromArray($path, $projectData);
        }

        // Load aliases
        $aliases = $data['aliases'] ?? [];

        return new self(
            currentProject: $currentProject,
            projects: $projects,
            aliases: $aliases,
        );
    }

    /**
     * Get aliases for a specific project path
     *
     * @return string[]
     */
    public function getAliasesForPath(string $projectPath): array
    {
        $result = [];

        foreach ($this->aliases as $alias => $path) {
            if ($path === $projectPath) {
                $result[] = $alias;
            }
        }

        return $result;
    }

    /**
     * Resolve a path or alias to the actual project path
     */
    public function resolvePathOrAlias(string $pathOrAlias): string
    {
        return $this->aliases[$pathOrAlias] ?? $pathOrAlias;
    }

    public function jsonSerialize(): array
    {
        $projects = [];
        foreach ($this->projects as $path => $project) {
            $projects[$path] = $project;
        }

        return [
            'current_project' => $this->currentProject,
            'projects' => $projects,
            'aliases' => $this->aliases,
        ];
    }
}
