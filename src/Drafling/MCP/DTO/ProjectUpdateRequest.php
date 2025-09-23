<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\MCP\DTO;

/**
 * Request DTO for updating an existing project
 */
final readonly class ProjectUpdateRequest
{
    /**
     * @param string|null $name Updated project name
     * @param string|null $description Updated project description
     * @param string|null $status Updated project status
     * @param string[]|null $tags Updated project tags
     * @param string[]|null $entryDirs Updated entry directories
     */
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?string $status = null,
        public ?array $tags = null,
        public ?array $entryDirs = null,
    ) {}

    /**
     * Check if request has any updates
     */
    public function hasUpdates(): bool
    {
        return $this->name !== null
            || $this->description !== null
            || $this->status !== null
            || $this->tags !== null
            || $this->entryDirs !== null;
    }
}
