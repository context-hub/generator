<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\MCP\DTO;

use Spiral\JsonSchemaGenerator\Attribute\Field;
use Spiral\JsonSchemaGenerator\Attribute\Constraint\Range;

/**
 * DTO for listing projects with filtering and pagination options
 */
final readonly class ListProjectsRequest
{
    public function __construct(
        #[Field(
            description: 'Project filtering criteria',
            default: null,
        )]
        public ?ProjectFilters $filters = null,
        #[Field(
            description: 'Maximum number of projects to return',
            default: 20,
        )]
        #[Range(min: 1, max: 100)]
        public int $limit = 20,
        #[Field(
            description: 'Number of projects to skip (for pagination)',
            default: 0,
        )]
        #[Range(min: 0, max: 10000)]
        public int $offset = 0,
        #[Field(
            description: 'Sort projects by field (name, status, created_at, updated_at)',
            default: 'name',
        )]
        public string $sortBy = 'name',
        #[Field(
            description: 'Sort direction (asc or desc)',
            default: 'asc',
        )]
        public string $sortDirection = 'asc',
    ) {}

    /**
     * Get filters as array for domain services
     */
    public function getFilters(): array
    {
        if ($this->filters === null) {
            return [];
        }

        return $this->filters->toArray();
    }

    /**
     * Get pagination options
     */
    public function getPaginationOptions(): array
    {
        return [
            'limit' => $this->limit,
            'offset' => $this->offset,
        ];
    }

    /**
     * Get sorting options
     */
    public function getSortingOptions(): array
    {
        return [
            'sort_by' => $this->sortBy,
            'sort_direction' => $this->sortDirection,
        ];
    }

    /**
     * Check if any filters are applied
     */
    public function hasFilters(): bool
    {
        return $this->filters !== null && $this->filters->hasFilters();
    }

    /**
     * Validate the request
     */
    public function validate(): array
    {
        $errors = [];

        // Validate pagination
        if ($this->limit < 1 || $this->limit > 100) {
            $errors[] = 'Limit must be between 1 and 100';
        }

        if ($this->offset < 0) {
            $errors[] = 'Offset must be non-negative';
        }

        // Validate sorting
        $validSortFields = ['name', 'status', 'created_at', 'updated_at', 'template'];
        if (!\in_array($this->sortBy, $validSortFields, true)) {
            $errors[] = 'Sort field must be one of: ' . \implode(', ', $validSortFields);
        }

        $validSortDirections = ['asc', 'desc'];
        if (!\in_array(\strtolower($this->sortDirection), $validSortDirections, true)) {
            $errors[] = 'Sort direction must be either "asc" or "desc"';
        }

        // Validate filters if provided
        if ($this->filters !== null) {
            $filterErrors = $this->filters->validate();
            $errors = \array_merge($errors, $filterErrors);
        }

        return $errors;
    }
}
