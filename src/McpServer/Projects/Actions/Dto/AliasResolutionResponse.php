<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Projects\Actions\Dto;

/**
 * Response when alias resolution occurs
 */
final readonly class AliasResolutionResponse implements \JsonSerializable
{
    public function __construct(
        public string $originalAlias,
        public string $resolvedPath,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'original_alias' => $this->originalAlias,
            'resolved_path' => $this->resolvedPath,
        ];
    }
}
