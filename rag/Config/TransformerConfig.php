<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Config;

final readonly class TransformerConfig
{
    public function __construct(
        public int $chunkSize = 1000,
        public int $overlap = 200,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            chunkSize: (int) ($data['chunk_size'] ?? 1000),
            overlap: (int) ($data['overlap'] ?? 200),
        );
    }
}
