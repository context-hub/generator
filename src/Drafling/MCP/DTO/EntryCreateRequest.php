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
     * Get the processed title for entry creation
     * This should be called by the service layer to ensure consistent title handling
     */
    public function getProcessedTitle(): string
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
     * @deprecated Use getProcessedTitle() instead for consistency
     */
    public function getTitle(): string
    {
        return $this->getProcessedTitle();
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

        // Validate tags if provided
        foreach ($this->tags as $tag) {
            if (!\is_string($tag) || empty(\trim($tag))) {
                $errors[] = 'All tags must be non-empty strings';
                break;
            }
        }

        return $errors;
    }

    /**
     * Create a copy with resolved internal keys (to be used by services after template lookup)
     */
    public function withResolvedKeys(
        string $resolvedCategory,
        string $resolvedEntryType,
        ?string $resolvedStatus = null,
    ): self {
        return new self(
            projectId: $this->projectId,
            category: $resolvedCategory,
            entryType: $resolvedEntryType,
            content: $this->content,
            title: $this->title,
            status: $resolvedStatus ?? $this->status,
            tags: $this->tags,
        );
    }
}
