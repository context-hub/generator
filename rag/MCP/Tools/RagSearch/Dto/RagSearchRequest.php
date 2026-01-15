<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\MCP\Tools\RagSearch\Dto;

use Spiral\JsonSchemaGenerator\Attribute\Constraint\Range;
use Spiral\JsonSchemaGenerator\Attribute\Field;

final readonly class RagSearchRequest
{
    public function __construct(
        #[Field(description: 'Search query in natural language')]
        public string $query,
        #[Field(description: 'Filter by type: architecture, api, testing, convention, tutorial, reference, general')]
        public ?string $type = null,
        #[Field(description: 'Filter by source path (exact or prefix match)')]
        public ?string $sourcePath = null,
        #[Field(description: 'Maximum number of results to return')]
        #[Range(min: 1, max: 50)]
        public int $limit = 10,
    ) {}
}
