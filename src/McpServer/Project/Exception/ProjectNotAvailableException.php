<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Project\Exception;

/**
 * Exception thrown when a requested project is not available.
 *
 * This exception is thrown when:
 * - The project is not in the whitelist (context.yaml)
 * - The project alias doesn't exist in .project-state.json
 */
final class ProjectNotAvailableException extends \RuntimeException
{
    public function __construct(
        public readonly string $projectName,
        public readonly array $availableProjects = [],
    ) {
        $message = \sprintf(
            "Project '%s' is not available.",
            $projectName,
        );

        if (!empty($availableProjects)) {
            $message .= \sprintf(
                ' Use projects-list to see available projects. Available: %s',
                \implode(', ', $availableProjects),
            );
        } else {
            $message .= ' Use projects-list to see available projects.';
        }

        parent::__construct($message);
    }
}
