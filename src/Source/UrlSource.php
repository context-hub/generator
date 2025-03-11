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

    public static function fromArray(array $data): self
    {
        if (!isset($data['urls']) || !\is_array($data['urls'])) {
            throw new \RuntimeException('URL source must have a "urls" array property');
        }

        return new self(
            urls: $data['urls'],
            description: $data['description'] ?? '',
            selector: $data['selector'] ?? null,
        );
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
     * @return (string|string[])[]
     *
     * @psalm-return array{type: 'url', urls?: array<string>, description?: string, selector?: string}
     */
    public function jsonSerialize(): array
    {
        return \array_filter([
            'type' => 'url',
            'urls' => $this->urls,
            'description' => $this->getDescription(),
            'selector' => $this->getSelector(),
        ]);
    }
}
