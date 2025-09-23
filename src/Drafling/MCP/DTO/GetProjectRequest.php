<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\MCP\DTO;

use Spiral\JsonSchemaGenerator\Attribute\Field;

/**
 * DTO for getting a single project by ID
 */
final readonly class GetProjectRequest
{
    public function __construct(
        #[Field(description: 'Project ID')]
        public string $id,
    ) {}

    /**
     * Validate the request
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->id)) {
            $errors[] = 'Project ID cannot be empty';
        }

        return $errors;
    }
}
