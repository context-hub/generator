<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\Dto;

use Spiral\JsonSchemaGenerator\Attribute\Field;

final readonly class FileWriteRequest
{
    public function __construct(
        #[Field(
            description: 'Relative path to the file (e.g., "src/file.txt"). Path is resolved against project root.',
        )]
        public string $path,
        #[Field(
            description: 'Content to write to the file',
        )]
        public string $content,
        #[Field(
            description: 'Create directory if it does not exist',
            default: true,
        )]
        public bool $createDirectory = true,
    ) {}
}
