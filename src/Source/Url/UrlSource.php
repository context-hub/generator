<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Url;

use Butschster\ContextGenerator\Source\BaseSource;

/**
 * Enhanced source for URLs with selector support
 */
final class UrlSource extends BaseSource
{
    /**
     * @param array<int, string> $urls URLs to fetch content from
     * @param string $description Human-readable description
     * @param string|null $selector CSS selector to extract specific content (null for full page)
     * @param array<non-empty-string> $tags
     */
    public function __construct(
        public readonly array $urls,
        string $description = '',
        public readonly ?string $selector = null,
        array $tags = [],
    ) {
        parent::__construct(description: $description, tags: $tags);
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
            tags: $data['tags'] ?? [],
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
            ...parent::jsonSerialize(),
            'urls' => $this->urls,
            'selector' => $this->getSelector(),
        ], static fn($value) => $value !== null && $value !== '' && $value !== []);
    }
}
