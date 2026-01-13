<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Project;

/**
 * Interface for request DTOs that support multi-project operations.
 *
 * Request DTOs implementing this interface can specify a project context
 * for their operations, allowing tools to work with different projects.
 */
interface ProjectAwareRequest
{
    /**
     * Get the project identifier (alias from .project-state.json).
     *
     * @return string|null Project alias, or null to use current project
     */
    public function getProject(): ?string;
}
