<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Docs\Dto;

use Spiral\JsonSchemaGenerator\Attribute\Field;

final readonly class LibrarySearchRequest
{
    public function __construct(
        #[Field(
            description: 'Provide a library name to search for relevant libraries id (Like "Spiral Framework", "Symfony", "Laravel", etc.)',
        )]
        public string $query,
        #[Field(
            description: 'Maximum number of results to return (default is 5)',
            default: 5,
        )]
        public ?int $maxResults = 5,
    ) {}
}