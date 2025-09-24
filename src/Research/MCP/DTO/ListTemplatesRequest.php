<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\MCP\DTO;

use Spiral\JsonSchemaGenerator\Attribute\Field;

/**
 * DTO for listing available templates
 */
final readonly class ListTemplatesRequest
{
    public function __construct(
        #[Field(
            description: 'Filter templates by tag (optional)',
            default: null,
        )]
        public ?string $tag = null,
        #[Field(
            description: 'Filter templates by name (partial match, optional)',
            default: null,
        )]
        public ?string $nameContains = null,
        #[Field(
            description: 'Include detailed template information (categories, entry types)',
            default: false,
        )]
        public bool $includeDetails = false,
    ) {}

    /**
     * Check if any filters are applied
     */
    public function hasFilters(): bool
    {
        return $this->tag !== null || $this->nameContains !== null;
    }

    /**
     * Validate the request
     */
    public function validate(): array
    {
        $errors = [];

        if ($this->tag !== null && empty(\trim($this->tag))) {
            $errors[] = 'Tag filter cannot be empty when provided';
        }

        if ($this->nameContains !== null && empty(\trim($this->nameContains))) {
            $errors[] = 'Name filter cannot be empty when provided';
        }

        return $errors;
    }
}
