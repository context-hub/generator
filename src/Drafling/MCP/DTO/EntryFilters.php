<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\MCP\DTO;

use Spiral\JsonSchemaGenerator\Attribute\Field;

/**
 * DTO for entry filtering criteria
 */
final readonly class EntryFilters
{
    public function __construct(
        #[Field(
            description: 'Filter by entry category',
            default: null,
        )]
        public ?string $category = null,
        #[Field(
            description: 'Filter by entry type',
            default: null,
        )]
        public ?string $entryType = null,
        #[Field(
            description: 'Filter by entry status',
            default: null,
        )]
        public ?string $status = null,
        #[Field(
            description: 'Filter by entry tags (entries must have any of these tags)',
            default: null,
        )]
        /** @var string[]|null */
        public ?array $tags = null,
        #[Field(
            description: 'Filter by entry title (partial match)',
            default: null,
        )]
        public ?string $titleContains = null,
        #[Field(
            description: 'Filter by entry description (partial match)',
            default: null,
        )]
        public ?string $descriptionContains = null,
        #[Field(
            description: 'Filter by entry content (partial match)',
            default: null,
        )]
        public ?string $contentContains = null,
    ) {}

    /**
     * Convert to array format expected by domain services
     */
    public function toArray(): array
    {
        $filters = [];

        if ($this->category !== null) {
            $filters['category'] = $this->category;
        }

        if ($this->entryType !== null) {
            $filters['entry_type'] = $this->entryType;
        }

        if ($this->status !== null) {
            $filters['status'] = $this->status;
        }

        if ($this->tags !== null && !empty($this->tags)) {
            $filters['tags'] = $this->tags;
        }

        if ($this->titleContains !== null) {
            $filters['title_contains'] = $this->titleContains;
        }

        if ($this->descriptionContains !== null) {
            $filters['description_contains'] = $this->descriptionContains;
        }

        if ($this->contentContains !== null) {
            $filters['content_contains'] = $this->contentContains;
        }

        return $filters;
    }

    /**
     * Check if any filters are applied
     */
    public function hasFilters(): bool
    {
        return $this->category !== null
            || $this->entryType !== null
            || $this->status !== null
            || ($this->tags !== null && !empty($this->tags))
            || $this->titleContains !== null
            || $this->descriptionContains !== null
            || $this->contentContains !== null;
    }

    /**
     * Validate the filters
     */
    public function validate(): array
    {
        $errors = [];

        // Validate tags array if provided
        if ($this->tags !== null) {
            if (!\is_array($this->tags)) {
                $errors[] = 'Tags must be an array';
            } elseif (empty($this->tags)) {
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

        // Validate text filters if provided
        $textFilters = [
            'titleContains' => $this->titleContains,
            'descriptionContains' => $this->descriptionContains,
            'contentContains' => $this->contentContains,
        ];

        foreach ($textFilters as $field => $value) {
            if ($value !== null && empty(\trim($value))) {
                $errors[] = "{$field} filter cannot be empty when provided";
            }
        }

        return $errors;
    }
}
