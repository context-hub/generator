<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Document;

use Symfony\AI\Store\Document\Metadata;

final readonly class MetadataFactory
{
    public function create(
        DocumentType $type = DocumentType::General,
        ?string $sourcePath = null,
        ?array $tags = null,
        array $extra = [],
    ): Metadata {
        return new Metadata([
            'type' => $type->value,
            'source_path' => $sourcePath,
            'tags' => $tags ?? [],
            'indexed_at' => (new \DateTimeImmutable())->format(\DATE_ATOM),
            ...$extra,
        ]);
    }
}
