<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\MCP\DTO;

use Spiral\JsonSchemaGenerator\Attribute\Field;

/**
 * DTO for reading a specific entry request
 */
final readonly class ReadEntryRequest
{
    public function __construct(
        #[Field(
            description: 'Project ID containing the entry',
            default: null,
        )]
        public string $projectId,
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

        // Validate project ID
        if (empty(\trim($this->projectId))) {
            $errors[] = 'Project ID is required';
        }

        // Validate entry ID
        if (empty(\trim($this->entryId))) {
            $errors[] = 'Entry ID is required';
        }

        return $errors;
    }
}
