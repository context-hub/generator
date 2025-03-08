<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source;

/**
 * Enhanced source for URLs with selector support
 */
final class UrlSource extends BaseSource
{
    /**
     * @param array<string> $urls URLs to fetch content from
     * @param string $description Human-readable description
     * @param string|null $selector CSS selector to extract specific content (null for full page)
     */
    public function __construct(
        public readonly array $urls,
        string $description = '',
        public readonly ?string $selector = null,
    ) {
        parent::__construct($description);
    }

    /**
     * Get the CSS selector for content extraction
     */
    public function getSelector(): ?string
    {
        return $this->selector;
    }

    /**
     * Check if a specific content selector is defined
     */
    public function hasSelector(): bool
    {
        return $this->selector !== null && \trim($this->selector) !== '';
    }

    /**
     * Create a new instance with a specific selector
     */
    public function withSelector(string $selector): self
    {
        return new self(
            urls: $this->urls,
            description: $this->getDescription(),
            selector: $selector,
        );
    }
}