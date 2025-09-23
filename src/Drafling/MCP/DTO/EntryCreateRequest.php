<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\MCP\DTO;

/**
 * Request DTO for creating a new entry
 */
final readonly class EntryCreateRequest
{
    /**
     * @param string $category Category name (must match template)
     * @param string $entryType Entry type key (must match template)
     * @param string $title Entry title
     * @param string $content Markdown content
     * @param string[] $tags Entry tags
     * @param string|null $status Override default status
     */
    public function __construct(
        public string $category,
        public string $entryType,
        public string $title,
        public string $content,
        public array $tags = [],
        public ?string $status = null,
    ) {}
}
