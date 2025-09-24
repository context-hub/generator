<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\MCP\DTO;

use Spiral\JsonSchemaGenerator\Attribute\Field;

/**
 * DTO for reading a specific entry request
 */
final readonly class ReadEntryRequest
{
    public function __construct(
        #[Field(
            description: 'Research ID containing the entry',
            default: null,
        )]
        public string $researchId,
        #[Field(
            description: 'Entry ID to retrieve',
            default: null,
        )]
        public string $entryId,
    ) {}

    /**
     * Validate the request
     */
    public function validate(): array
    {
        $errors = [];

        // Validate research ID
        if (empty(\trim($this->researchId))) {
            $errors[] = 'Research ID is required';
        }

        // Validate entry ID
        if (empty(\trim($this->entryId))) {
            $errors[] = 'Entry ID is required';
        }

        return $errors;
    }
}
