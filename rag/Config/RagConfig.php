<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Config;

final readonly class RagConfig
{
    public function __construct(
        public bool $enabled = false,
        public StoreConfig $store = new StoreConfig(),
        public VectorizerConfig $vectorizer = new VectorizerConfig(),
        public TransformerConfig $transformer = new TransformerConfig(),
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            enabled: (bool) ($data['enabled'] ?? false),
            store: StoreConfig::fromArray($data['store'] ?? []),
            vectorizer: VectorizerConfig::fromArray($data['vectorizer'] ?? []),
            transformer: TransformerConfig::fromArray($data['transformer'] ?? []),
        );
    }
}
