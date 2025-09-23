<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\MCP\DTO;

/**
 * Request DTO for updating an existing entry
 */
final readonly class EntryUpdateRequest
{
    /**
     * @param string|null $title Updated entry title
     * @param string|null $status Updated entry status
     * @param string[]|null $tags Updated entry tags
     * @param string|null $content Updated markdown content
     */
    public function __construct(
        public ?string $title = null,
        public ?string $status = null,
        public ?array $tags = null,
        public ?string $content = null,
    ) {}

    /**
     * Check if request has any updates
     */
    public function hasUpdates(): bool
    {
        return $this->title !== null
            || $this->status !== null
            || $this->tags !== null
            || $this->content !== null;
    }
}
