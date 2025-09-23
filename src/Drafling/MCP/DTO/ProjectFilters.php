<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\MCP\DTO;

use Spiral\JsonSchemaGenerator\Attribute\Field;
use Spiral\JsonSchemaGenerator\Attribute\Constraint\Enum;

/**
 * DTO for project filtering criteria
 */
final readonly class ProjectFilters
{
    public function __construct(
        #[Field(
            description: 'Filter by project status',
            default: null,
        )]
        #[Enum(values: ['draft', 'active', 'published', 'archived'])]
        public ?string $status = null,
        #[Field(
            description: 'Filter by template/project type',
            default: null,
        )]
        public ?string $template = null,
        #[Field(
            description: 'Filter by project tags (projects must have any of these tags)',
            default: null,
        )]
        /** @var string[]|null */
        public ?array $tags = null,
        #[Field(
            description: 'Filter by project name (partial match)',
            default: null,
        )]
        public ?string $nameContains = null,
    ) {}

    /**
     * Convert to array format expected by domain services
     */
    public function toArray(): array
    {
        $filters = [];

        if ($this->status !== null) {
            $filters['status'] = $this->status;
        }

        if ($this->template !== null) {
            $filters['template'] = $this->template;
        }

        if ($this->tags !== null && !empty($this->tags)) {
            $filters['tags'] = $this->tags;
        }

        if ($this->nameContains !== null) {
            $filters['name_contains'] = $this->nameContains;
        }

        return $filters;
    }

    /**
     * Check if any filters are applied
     */
    public function hasFilters(): bool
    {
        return $this->status !== null
            || $this->template !== null
            || ($this->tags !== null && !empty($this->tags))
            || $this->nameContains !== null;
    }

    /**
     * Validate the filters
     */
    public function validate(): array
    {
        $errors = [];

        // Validate tags array if provided
        if ($this->tags !== null) {
            if (empty($this->tags)) {
                $errors[] = 'Tags array cannot be empty when provided';
            } else {
                foreach ($this->tags as $tag) {
                    if (!\is_string($tag) || empty(\trim($tag))) {
                        $errors[] = 'All tags must be non-empty strings';
                        break;
                    }
                }
            }
        }

        // Validate nameContains if provided
        if ($this->nameContains !== null && empty(\trim($this->nameContains))) {
            $errors[] = 'Name filter cannot be empty when provided';
        }

        return $errors;
    }
}
