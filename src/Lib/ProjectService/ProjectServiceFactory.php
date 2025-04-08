<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\ProjectService;

use Spiral\Boot\EnvironmentInterface;

class ProjectServiceFactory
{
    public static function create(EnvironmentInterface $env): ProjectServiceInterface
    {
        return new ProjectService(
            projectName: $env->get('MCP_PROJECT_NAME'),
            projectPrefix: $env->get('MCP_PROJECT_PREFIX'),
        );

    }
}
