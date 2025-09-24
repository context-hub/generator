<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\MCP\DTO;

use Spiral\JsonSchemaGenerator\Attribute\Field;

/**
 * DTO for listing entries request
 */
final readonly class ListEntriesRequest
{
    public function __construct(
        #[Field(
            description: 'Research ID to list entries from',
            default: null,
        )]
        public string $researchId,
        #[Field(
            description: 'Entry filtering criteria',
            default: null,
        )]
        public ?EntryFilters $filters = null,
        #[Field(
            description: 'Maximum number of entries to return',
            default: 50,
        )]
        public int $limit = 50,
        #[Field(
            description: 'Number of entries to skip for pagination',
            default: 0,
        )]
        public int $offset = 0,
    ) {}

    /**
     * Validate the request
     */
    public function validate(): array
    {
        $errors = [];

        if (empty(\trim($this->researchId))) {
            $errors[] = 'Research ID is required';
        }


        // Validate pagination parameters
        if ($this->limit < 1 || $this->limit > 200) {
            $errors[] = 'Limit must be between 1 and 200';
        }

        if ($this->offset < 0) {
            $errors[] = 'Offset must be non-negative';
        }

        // Validate filters if provided
        if ($this->filters !== null) {
            $filterErrors = $this->filters->validate();
            $errors = \array_merge($errors, $filterErrors);
        }

        return $errors;
    }

    /**
     * Check if filters are applied
     */
    public function hasFilters(): bool
    {
        return $this->filters !== null && $this->filters->hasFilters();
    }

    /**
     * Get filters as array
     */
    public function getFilters(): array
    {
        return $this->filters?->toArray() ?? [];
    }
}
