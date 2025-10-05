<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\Dto;

use Spiral\JsonSchemaGenerator\Attribute\Field;

final readonly class FileReplaceContentRequest
{
    public function __construct(
        #[Field(
            description: 'Path to the file, relative to project root. Only files within project directory can be accessed.',
        )]
        public string $path,
        #[Field(
            description: 'Exact content to find in the file. Must appear exactly once. Include enough surrounding context to ensure uniqueness. Whitespace and special characters must match exactly.',
        )]
        public string $search,
        #[Field(
            description: 'Content to replace the matched text with. Can include the original text plus additions for insertions.',
        )]
        public string $replace,
    ) {}
}
