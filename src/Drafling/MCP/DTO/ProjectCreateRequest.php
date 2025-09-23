<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\MCP\DTO;

use Spiral\JsonSchemaGenerator\Attribute\Field;

/**
 * Data Transfer Object for project creation requests
 */
final readonly class ProjectCreateRequest
{
    public function __construct(
        #[Field(description: 'Template ID to use for the project')]
        public string $templateId,
        #[Field(description: 'Project title')]
        public string $title,
        #[Field(
            description: 'Project description (optional)',
            default: '',
        )]
        public string $description = '',
        #[Field(
            description: 'Project tags for organization (optional)',
            default: [],
        )]
        /** @var string[] */
        public array $tags = [],
        #[Field(
            description: 'Entry directories to create (optional)',
            default: [],
        )]
        /** @var string[] */
        public array $entryDirs = [],
        #[Field(
            description: 'Initial memory entries (optional)',
            default: [],
        )]
        /** @var string[] */
        public array $memory = [],
    ) {}

    /**
     * Get project name (using title field)
     */
    public function getName(): string
    {
        return $this->title;
    }

    /**
     * Validate the request data
     */
    public function validate(): array
    {
        $errors = [];

        if (empty(\trim($this->templateId))) {
            $errors[] = 'Template ID cannot be empty';
        }

        if (empty(\trim($this->title))) {
            $errors[] = 'Project title cannot be empty';
        }

        return $errors;
    }
}
