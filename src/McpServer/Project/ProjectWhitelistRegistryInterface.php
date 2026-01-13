<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Project;

/**
 * Registry for whitelisted projects from context.yaml.
 *
 * This registry stores projects that have been configured in context.yaml
 * and validated against .project-state.json aliases.
 */
interface ProjectWhitelistRegistryInterface
{
    /**
     * Check if a project is in the whitelist.
     *
     * @param string $projectName Project alias to check
     */
    public function isAllowed(string $projectName): bool;

    /**
     * Get all whitelisted projects.
     *
     * @return ProjectConfig[]
     */
    public function getProjects(): array;

    /**
     * Check if the whitelist is empty.
     */
    public function isEmpty(): bool;
}
