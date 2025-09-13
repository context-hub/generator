<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Docs\Dto;

use Spiral\JsonSchemaGenerator\Attribute\Field;

final readonly class FetchLibraryDocsRequest
{
    public function __construct(
        #[Field(
            description: 'The library ID to fetch documentation for. Use "library-search" tool to find library IDs by name.',
        )]
        public string $id,
        #[Field(
            description: 'Maximum number of tokens to return',
            default: 3000,
        )]
        public int $tokens = 3000,
        #[Field(
            description: 'Specific topic to focus on (optional)',
        )]
        public ?string $topic = null,
    ) {}
}