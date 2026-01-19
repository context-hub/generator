<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Project;

use Butschster\ContextGenerator\McpServer\Project\Exception\ProjectPathException;

/**
 * Resolves and validates project paths from YAML configuration.
 */
interface ProjectPathResolverInterface
{
    /**
     * Resolve a path to an absolute, validated path.
     *
     * @param string $path Raw path from YAML (relative or absolute)
     * @param string $contextDir Directory containing context.yaml
     * @return string Resolved absolute path
     * @throws ProjectPathException If path is invalid
     */
    public function resolve(string $path, string $contextDir): string;
}
