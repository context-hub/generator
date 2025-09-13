<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\Dto;

use Spiral\JsonSchemaGenerator\Attribute\Field;

final readonly class FileRenameRequest
{
    public function __construct(
        #[Field(
            description: 'Path to the file, relative to project root. Only files within project directory can be accessed.',
        )]
        public string $path,
        #[Field(
            description: 'Path to the new file, relative to project root. Only files within project directory can be accessed.',
        )]
        public string $newPath,
    ) {}
}