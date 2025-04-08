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
        $projectPrefix = $this->env->get('MCP_PROJECT_PREFIX');
        if ($projectName !== null && $projectPrefix === null) {
            $projectPrefix = \preg_replace('#[^a-z0-9]+#i', '', (string) $projectName);
        }

        return new ProjectService(
            projectName: $projectName,
            projectPrefix: $projectPrefix,
        );
    }
}
