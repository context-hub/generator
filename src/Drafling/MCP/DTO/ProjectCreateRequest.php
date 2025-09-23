<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\MCP\DTO;

/**
 * Request DTO for creating a new project
 */
final readonly class ProjectCreateRequest
{
    /**
     * @param string $templateId Template key to base project on
     * @param string $name Project name
     * @param string $description Project description
     * @param string[] $tags Project tags
     * @param string[] $entryDirs Directories to create for entries
     */
    public function __construct(
        public string $templateId,
        public string $name,
        public string $description,
        public array $tags = [],
        public array $entryDirs = [],
    ) {}
}
