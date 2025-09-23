<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\MCP\DTO;

use Spiral\JsonSchemaGenerator\Attribute\Field;

/**
 * Data Transfer Object for entry creation requests
 */
final readonly class EntryCreateRequest
{
    public function __construct(
        #[Field(description: 'Project ID')]
        public string $projectId,
        #[Field(description: 'Category name (accepts display names)')]
        public string $category,
        #[Field(description: 'Entry type (accepts display names)')]
        public string $entryType,
        #[Field(description: 'Entry content')]
        public string $content,
        #[Field(
            description: 'Entry title (optional)',
            default: null,
        )]
        public ?string $title = null,
        #[Field(
            description: 'Entry status (optional, accepts display names)',
            default: null,
        )]
        public ?string $status = null,
        #[Field(
            description: 'Entry tags (optional)',
            default: [],
        )]
        /** @var string[] */
        public array $tags = [],
    ) {}

    /**
     * Get the entry title, generating from content if not provided
     */
    public function getTitle(): string
    {
        if ($this->title !== null && !empty(\trim($this->title))) {
            return \trim($this->title);
        }

        // Generate title from first line of content
        $lines = \explode("\n", \trim($this->content));
        $firstLine = \trim($lines[0] ?? '');

        if (empty($firstLine)) {
            return 'Untitled Entry';
        }

        // Remove markdown heading markers
        $title = \preg_replace('/^#+\s*/', '', $firstLine);

        // Limit title length
        if (\strlen((string) $title) > 100) {
            $title = \substr((string) $title, 0, 100) . '...';
        }

        return \trim((string) $title) ?: 'Untitled Entry';
    }

    /**
     * Validate the request data
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->projectId)) {
            $errors[] = 'Project ID cannot be empty';
        }

        if (empty($this->category)) {
            $errors[] = 'Category cannot be empty';
        }

        if (empty($this->entryType)) {
            $errors[] = 'Entry type cannot be empty';
        }

        if (empty(\trim($this->content))) {
            $errors[] = 'Content cannot be empty';
        }

        return $errors;
    }
}
