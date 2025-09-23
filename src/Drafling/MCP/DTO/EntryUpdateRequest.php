<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\MCP\DTO;

use Spiral\JsonSchemaGenerator\Attribute\Field;

/**
 * Data Transfer Object for entry update requests
 */
final readonly class EntryUpdateRequest
{
    public function __construct(
        #[Field(description: 'Project ID')]
        public string $projectId,
        #[Field(description: 'Entry ID to update')]
        public string $entryId,
        #[Field(
            description: 'New title (optional)',
        )]
        public ?string $title = null,
        #[Field(
            description: 'New content (optional)',
        )]
        public ?string $content = null,
        #[Field(
            description: 'New status (optional, accepts display names)',
        )]
        public ?string $status = null,
        #[Field(
            description: 'New content type (optional)',
        )]
        public ?string $contentType = null,
        #[Field(
            description: 'New tags (optional)',
            default: null,
        )]
        /** @var string[]|null */
        public ?array $tags = null,
        #[Field(
            description: 'Find and replace in content (optional)',
            default: null,
        )]
        public ?TextReplaceRequest $textReplace = null,
    ) {}

    /**
     * Check if there are any updates to apply
     */
    public function hasUpdates(): bool
    {
        return $this->title !== null
            || $this->content !== null
            || $this->status !== null
            || $this->contentType !== null
            || $this->tags !== null
            || $this->textReplace !== null;
    }

    /**
     * Get processed content applying text replacement if needed
     * This method should be called by the service layer to ensure proper content handling
     */
    public function getProcessedContent(?string $existingContent = null): ?string
    {
        $baseContent = $this->content ?? $existingContent;

        if ($baseContent === null || $this->textReplace === null) {
            return $this->content;
        }

        return \str_replace($this->textReplace->find, $this->textReplace->replace, $baseContent);
    }

    /**
     * Get the final content that should be saved
     * Considers both direct content updates and text replacement operations
     */
    public function getFinalContent(?string $existingContent = null): ?string
    {
        // If we have direct content update, use it as base
        if ($this->content !== null) {
            $baseContent = $this->content;
        } else {
            $baseContent = $existingContent;
        }

        // Apply text replacement if specified
        if ($this->textReplace !== null && $baseContent !== null) {
            return \str_replace($this->textReplace->find, $this->textReplace->replace, $baseContent);
        }

        return $this->content; // Return direct content update or null
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

        if (empty($this->entryId)) {
            $errors[] = 'Entry ID cannot be empty';
        }

        if (!$this->hasUpdates()) {
            $errors[] = 'At least one field must be provided for update';
        }

        // Validate tags if provided
        if ($this->tags !== null) {
            if (!\is_array($this->tags)) {
                $errors[] = 'Tags must be an array';
            } else {
                foreach ($this->tags as $tag) {
                    if (!\is_string($tag) || empty(\trim($tag))) {
                        $errors[] = 'All tags must be non-empty strings';
                        break;
                    }
                }
            }
        }

        // Validate text replace if provided
        if ($this->textReplace !== null) {
            $replaceErrors = $this->textReplace->validate();
            $errors = \array_merge($errors, $replaceErrors);
        }

        return $errors;
    }

    /**
     * Create a copy with resolved internal keys (to be used by services after template lookup)
     */
    public function withResolvedStatus(?string $resolvedStatus): self
    {
        return new self(
            projectId: $this->projectId,
            entryId: $this->entryId,
            title: $this->title,
            content: $this->content,
            status: $resolvedStatus,
            contentType: $this->contentType,
            tags: $this->tags,
            textReplace: $this->textReplace,
        );
    }
}

/**
 * Nested DTO for text replace operations
 */
final readonly class TextReplaceRequest
{
    public function __construct(
        #[Field(description: 'Text to find')]
        public string $find,
        #[Field(description: 'Replacement text')]
        public string $replace,
    ) {}

    /**
     * Validate text replace request
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->find)) {
            $errors[] = 'Find text cannot be empty for text replacement';
        }

        // Note: replace text can be empty (for deletion)

        return $errors;
    }
}
