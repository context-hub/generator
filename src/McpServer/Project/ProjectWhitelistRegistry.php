<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Project;

use Spiral\Core\Attribute\Singleton;

/**
 * Registry implementation for whitelisted projects.
 *
 * Stores projects configured in context.yaml that have been
 * validated against .project-state.json aliases.
 */
#[Singleton]
final class ProjectWhitelistRegistry implements ProjectWhitelistRegistryInterface
{
    /** @var array<string, ProjectConfig> */
    private array $projects = [];

    /**
     * Register a project in the whitelist.
     *
     * @param ProjectConfig $project Project configuration to register
     */
    public function register(ProjectConfig $project): self
    {
        $this->projects[$project->name] = $project;

        return $this;
    }

    public function isAllowed(string $projectName): bool
    {
        return isset($this->projects[$projectName]);
    }

    public function get(string $projectName): ?ProjectConfig
    {
        return $this->projects[$projectName] ?? null;
    }

    public function getProjects(): array
    {
        return \array_values($this->projects);
    }

    public function isEmpty(): bool
    {
        return empty($this->projects);
    }

    /**
     * Clear all registered projects.
     *
     * Useful for testing or reconfiguration.
     */
    public function clear(): self
    {
        $this->projects = [];

        return $this;
    }
}
