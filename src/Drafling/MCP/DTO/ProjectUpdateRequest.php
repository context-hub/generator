<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\MCP\DTO;

use Spiral\JsonSchemaGenerator\Attribute\Field;

/**
 * Data Transfer Object for project update requests
 */
final readonly class ProjectUpdateRequest
{
    public function __construct(
        #[Field(description: 'Project ID to update')]
        public string $projectId,
        #[Field(
            description: 'New project title (optional)',
        )]
        public ?string $title = null,
        #[Field(
            description: 'New project description (optional)',
        )]
        public ?string $description = null,
        #[Field(
            description: 'New project status (optional)',
        )]
        public ?string $status = null,
        #[Field(
            description: 'New project tags (optional)',
            default: null,
        )]
        /** @var string[]|null */
        public ?array $tags = null,
        #[Field(
            description: 'New entry directories (optional)',
            default: null,
        )]
        /** @var string[]|null */
        public ?array $entryDirs = null,
        #[Field(
            description: 'New memory entries (optional)',
            default: null,
        )]
        /** @var ProjectMemory[]|null */
        public ?array $memory = null,
    ) {}

    /**
     * Get project name (alias for title)
     */
    public function getName(): ?string
    {
        return $this->title;
    }

    /**
     * Check if there are any updates to apply
     */
    public function hasUpdates(): bool
    {
        return $this->title !== null
            || $this->description !== null
            || $this->status !== null
            || $this->tags !== null
            || $this->entryDirs !== null
            || $this->memory !== null;
    }

    /**
     * Validate the request data
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->projectId)) {
            $errors[] = 'Project ID cannot be empty';
        }

        if (!$this->hasUpdates()) {
            $errors[] = 'At least one field must be provided for update';
        }

        return $errors;
    }
}
