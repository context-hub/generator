<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\MCP\DTO;

use Spiral\JsonSchemaGenerator\Attribute\Field;

/**
 * Data Transfer Object for adding memory entries to projects
 */
final readonly class AddProjectMemoryRequest
{
    public function __construct(
        #[Field(description: 'Project ID to add memory to')]
        public string $projectId,
        #[Field(description: 'Memory entry to add to the project')]
        public string $memory,
    ) {}

    /**
     * Validate the request data
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->projectId)) {
            $errors[] = 'Project ID cannot be empty';
        }

        if (empty(\trim($this->memory))) {
            $errors[] = 'Memory entry cannot be empty';
        }

        return $errors;
    }
}
