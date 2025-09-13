<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\Dto;

use Spiral\JsonSchemaGenerator\Attribute\Field;

final readonly class FileMoveRequest
{
    public function __construct(
        #[Field(
            description: 'Path to the source file, relative to project root. Only files within project directory can be accessed.',
        )]
        public string $source,
        #[Field(
            description: 'Path to the destination file, relative to project root. Only files within project directory can be accessed.',
        )]
        public string $destination,
        #[Field(
            description: 'Create directory if it does not exist',
            default: true,
        )]
        public bool $createDirectory = true,
    ) {}
}