<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\ProjectService;

use Spiral\Boot\EnvironmentInterface;

final readonly class ProjectServiceFactory
{
    public function __construct(
        private EnvironmentInterface $env,
    ) {}

    public function create(): ProjectServiceInterface
    {
        $projectName = $this->env->get('MCP_PROJECT_NAME');
        $projectSlug = $this->env->get('MCP_PROJECT_SLUG');
        if ($projectName !== null && $projectSlug === null) {
            $projectSlug = \preg_replace('#[^a-z0-9]+#i', '', (string) $projectName);
        }

        return new ProjectService(
            projectName: $projectName,
            projectSlug: $projectSlug,
        );
    }
}
