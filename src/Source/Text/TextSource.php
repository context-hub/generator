<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Text;

use Butschster\ContextGenerator\Source\BaseSource;

/**
 * Source for plain text content
 */
final class TextSource extends BaseSource
{
    /**
     * @param string $description Human-readable description
     * @param string $content Text content
     */
    public function __construct(
        public readonly string $content,
        string $description = '',
        public readonly string $tag = 'INSTRUCTION',
        array $tags = [],
    ) {
        parent::__construct(description: $description, tags: $tags);
    }

    public static function fromArray(array $data): self
    {
        if (!isset($data['content']) || !\is_string($data['content'])) {
            throw new \RuntimeException('Text source must have a "content" string property');
        }

        return new self(
            content: $data['content'],
            description: $data['description'] ?? '',
            tag: $data['tag'] ?? 'INSTRUCTION',
            tags: $data['tags'] ?? [],
        );
    }

    public function jsonSerialize(): array
    {
        return \array_filter([
            'type' => 'text',
            ...parent::jsonSerialize(),
            'content' => $this->content,
            'tag' => $this->tag,
        ], static fn($value) => $value !== null && $value !== '' && $value !== []);
    }
}
