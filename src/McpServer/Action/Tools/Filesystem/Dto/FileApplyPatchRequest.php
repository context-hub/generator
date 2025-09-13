<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\Dto;

use Spiral\JsonSchemaGenerator\Attribute\Field;

final readonly class FileApplyPatchRequest
{
    public function __construct(
        #[Field(
            description: 'Path to the file to modify, relative to project root',
        )]
        public string $path,
        /**
         * @var FileApplyPatchChunk[]
         */
        #[Field(
            description: 'Array of change chunks to apply to the file',
        )]
        public array $chunks,
    ) {}
}
