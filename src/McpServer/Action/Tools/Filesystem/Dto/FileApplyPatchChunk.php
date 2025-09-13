<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\Dto;

use Spiral\JsonSchemaGenerator\Attribute\Field;

final readonly class FileApplyPatchChunk
{
    public function __construct(
        #[Field(
            description: 'Context marker line to help locate the change position (e.g., "@@ class Calculator")',
        )]
        public string $contextMarker,
        /**
         * @var string[]
         */
        #[Field(
            description: 'Array of change lines: " " for context, "+" for additions, "-" for deletions. Pay attention to the indentation level. You need to provide the same indentation level as in the original file.',
        )]
        public array $changes,
    ) {}
}
