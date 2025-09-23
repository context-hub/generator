<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Domain\Model;

/**
 * Entry represents an individual markdown document with structured metadata
 */
final readonly class Entry implements \JsonSerializable
{
    /**
     * @param string $entryId Unique entry identifier (UUID)
     * @param string $title Entry title
     * @param string $description Short description for LLM understanding (max 200 chars)
     * @param string $entryType Entry type key (must match template)
     * @param string $category Category name (must match template)
     * @param string $status Current entry status
     * @param \DateTime $createdAt Creation timestamp
     * @param \DateTime $updatedAt Last update timestamp
     * @param string[] $tags Entry tags for organization
     * @param string $content Markdown content body
     * @param string|null $filePath Optional file path for storage reference
     */
    public function __construct(
        public string $entryId,
        public string $title,
        public string $description,
        public string $entryType,
        public string $category,
        public string $status,
        public \DateTime $createdAt,
        public \DateTime $updatedAt,
        public array $tags,
        public string $content,
        public ?string $filePath = null,
    ) {}

    /**
     * Create updated entry with new values
     */
    public function withUpdates(
        ?string $title = null,
        ?string $description = null,
        ?string $status = null,
        ?array $tags = null,
        ?string $content = null,
    ): self {
        return new self(
            entryId: $this->entryId,
            title: $title ?? $this->title,
            description: $description ?? $this->description,
            entryType: $this->entryType,
            category: $this->category,
            status: $status ?? $this->status,
            createdAt: $this->createdAt,
            updatedAt: new \DateTime(),
            tags: $tags ?? $this->tags,
            content: $content ?? $this->content,
            filePath: $this->filePath,
        );
    }

    /**
     * Generate filename for this entry
     */
    public function generateFilename(): string
    {
        $slug = \preg_replace('/[^a-z0-9]+/', '-', \strtolower($this->title));
        $slug = \trim((string) $slug, '-');

        return "{$slug}.md";
    }

    /**
     * Specify data which should be serialized to JSON
     */
    public function jsonSerialize(): array
    {
        return [
            'entry_id' => $this->entryId,
            'title' => $this->title,
            'description' => $this->description,
            'entry_type' => $this->entryType,
            'category' => $this->category,
            'status' => $this->status,
            'tags' => $this->tags,
            'content' => $this->content,
        ];
    }
}
