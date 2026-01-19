<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Service;

use Symfony\AI\Store\Document\VectorDocument;

final readonly class SearchResultItem
{
    public function __construct(
        public string $id,
        public string $content,
        public float $score,
        public string $type,
        public ?string $sourcePath,
        public array $tags,
        public string $indexedAt,
    ) {}

    public static function fromVectorDocument(VectorDocument $doc): self
    {
        $metadata = $doc->metadata;

        return new self(
            id: $doc->id->toRfc4122(),
            content: $metadata->getText() ?? '',
            score: $doc->score ?? 0.0,
            type: $metadata['type'] ?? 'general',
            sourcePath: $metadata['source_path'] ?? null,
            tags: $metadata['tags'] ?? [],
            indexedAt: $metadata['indexed_at'] ?? '',
        );
    }

    public function format(): string
    {
        $header = \sprintf('[%.2f] %s', $this->score, $this->type);
        if ($this->sourcePath !== null) {
            $header .= \sprintf(' | %s', $this->sourcePath);
        }

        $content = \mb_strlen($this->content) > 500
            ? \mb_substr($this->content, 0, 500) . '...'
            : $this->content;

        return "{$header}\n{$content}";
    }
}
