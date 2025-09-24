<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\MCP\DTO;

use Spiral\JsonSchemaGenerator\Attribute\Field;

/**
 * DTO for getting a single research by ID
 */
final readonly class GetResearchRequest
{
    public function __construct(
        #[Field(description: 'Research ID')]
        public string $id,
    ) {}

    /**
     * Validate the request
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->id)) {
            $errors[] = 'Research ID cannot be empty';
        }

        return $errors;
    }
}
