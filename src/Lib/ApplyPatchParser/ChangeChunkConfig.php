<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\ApplyPatchParser;

use Spiral\JsonSchemaGenerator\Attribute\Field;

final readonly class ChangeChunkConfig
{
    public function __construct(
        #[Field(
            description: 'Enable case-sensitive matching',
            default: true,
        )]
        public bool $caseSensitive = true,
        #[Field(
            description: 'Preserve exact whitespace formatting',
            default: false,
        )]
        public bool $preserveWhitespace = false,
        #[Field(
            description: 'Maximum number of lines to search for context markers',
            default: 100,
        )]
        public int $maxSearchLines = 100,
        #[Field(
            description: 'Minimum confidence score required for fuzzy matching (0.0-1.0)',
            default: 0.7,
        )]
        public float $minConfidence = 0.7,
    ) {}
}
