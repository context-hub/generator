<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\MCP\DTO;

use Spiral\JsonSchemaGenerator\Attribute\Field;
use Spiral\JsonSchemaGenerator\Attribute\Constraint\Range;

/**
 * DTO for listing researches with filtering and pagination options
 */
final readonly class ListResearchesRequest
{
    public function __construct(
        #[Field(
            description: 'Research filtering criteria',
            default: null,
        )]
        public ?ResearchFilters $filters = null,
        #[Field(
            description: 'Maximum number to return',
            default: 20,
        )]
        #[Range(min: 1, max: 100)]
        public int $limit = 20,
        #[Field(
            description: 'Number to skip (for pagination)',
            default: 0,
        )]
        #[Range(min: 0, max: 10000)]
        public int $offset = 0,
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

        // Validate filters if provided
        if ($this->filters !== null) {
            $filterErrors = $this->filters->validate();
            $errors = \array_merge($errors, $filterErrors);
        }

        return $errors;
    }
}
