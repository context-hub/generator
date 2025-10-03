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
     * @param non-empty-string $content Text content
     * @param array<non-empty-string> $tags
     */
    public function __construct(
        public readonly string $content,
        string $description = '',
        public readonly string $tag = 'INSTRUCTION',
        array $tags = [],
    ) {
        parent::__construct(description: $description, tags: $tags);
    }

    #[\Override]
    public function jsonSerialize(): array
    {
        return \array_filter(array: [
            'type' => 'text',
            ...parent::jsonSerialize(),
            'content' => $this->content,
            'tag' => $this->tag,
        ], callback: static fn($value) => $value !== null && $value !== '' && $value !== []);
    }
}
