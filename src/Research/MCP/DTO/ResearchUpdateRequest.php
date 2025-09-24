<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\MCP\DTO;

use Spiral\JsonSchemaGenerator\Attribute\Field;

/**
 * Data Transfer Object for research update requests
 */
final readonly class ResearchUpdateRequest
{
    public function __construct(
        #[Field(description: 'Research ID to update')]
        public string $researchId,
        #[Field(
            description: 'New research title (optional)',
        )]
        public ?string $title = null,
        #[Field(
            description: 'New research description (optional)',
        )]
        public ?string $description = null,
        #[Field(
            description: 'New research status (optional)',
        )]
        public ?string $status = null,
        #[Field(
            description: 'New research tags (optional)',
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
        /** @var ResearchMemory[]|null */
        public ?array $memory = null,
    ) {}

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

        if (empty($this->researchId)) {
            $errors[] = 'Research ID cannot be empty';
        }

        if (!$this->hasUpdates()) {
            $errors[] = 'At least one field must be provided for update';
        }

        return $errors;
    }
}
