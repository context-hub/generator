<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\MCP\DTO;

use Spiral\JsonSchemaGenerator\Attribute\Field;

final readonly class ResearchCreateRequest
{
    public function __construct(
        #[Field(description: 'Template ID to use for the research')]
        public string $templateId,
        #[Field(description: 'Research title')]
        public string $title,
        #[Field(
            description: 'Research description (optional)',
            default: '',
        )]
        public string $description = '',
        #[Field(
            description: 'Research tags for organization (optional)',
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
     * Validate the request data
     */
    public function validate(): array
    {
        $errors = [];

        if (empty(\trim($this->templateId))) {
            $errors[] = 'Template ID cannot be empty';
        }

        if (empty(\trim($this->title))) {
            $errors[] = 'Research title cannot be empty';
        }

        return $errors;
    }
}
