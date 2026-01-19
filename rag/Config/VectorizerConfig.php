<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Config;

final readonly class VectorizerConfig
{
    public function __construct(
        public string $platform = 'openai',
        public string $model = 'text-embedding-3-small',
        public string $apiKey = '',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            platform: (string) ($data['platform'] ?? 'openai'),
            model: (string) ($data['model'] ?? 'text-embedding-3-small'),
            apiKey: (string) ($data['api_key'] ?? ''),
        );
    }
}
