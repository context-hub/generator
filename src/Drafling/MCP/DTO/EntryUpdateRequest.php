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
     */
    public function getProcessedContent(?string $existingContent = null): ?string
    {
        $content = $this->content ?? $existingContent;

        if ($content === null || $this->textReplace === null) {
            return $this->content;
        }

        return \str_replace($this->textReplace->find, $this->textReplace->replace, $content);
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

        if ($this->textReplace !== null) {
            $replaceErrors = $this->textReplace->validate();
            $errors = \array_merge($errors, $replaceErrors);
        }

        return $errors;
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

        return $errors;
    }
}
