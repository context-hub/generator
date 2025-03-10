<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source;

/**
 * Source for plain text content
 */
final class TextSource extends BaseSource
{
    public static function fromArray(array $data): self
    {
        if (!isset($data['content']) || !\is_string($data['content'])) {
            throw new \RuntimeException('Text source must have a "content" string property');
        }

        return new self(
            content: $data['content'],
            description: $data['description'] ?? '',
        );
    }

    /**
     * @param string $description Human-readable description
     * @param string $content Text content
     */
    public function __construct(
        public readonly string $content,
        string $description = '',
    ) {
        parent::__construct($description);
    }

    public function jsonSerialize(): array
    {
        return \array_filter([
            'type' => 'text',
            'description' => $this->description,
            'content' => $this->content,
        ]);
    }
}
