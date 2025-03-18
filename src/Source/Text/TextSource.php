<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Text;

use Butschster\ContextGenerator\Schema\Attribute\SchemaType;
use Butschster\ContextGenerator\Source\BaseSource;

/**
 * Source for plain text content
 */
#[SchemaType(name: 'text-source', description: 'Text source - includes custom text content')]
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
    ) {
        parent::__construct($description);
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
        );
    }

    public function jsonSerialize(): array
    {
        return \array_filter([
            'type' => 'text',
            'description' => $this->description,
            'content' => $this->content,
            'tag' => $this->tag,
        ]);
    }
}
