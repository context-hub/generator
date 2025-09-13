<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\Dto;

use Spiral\JsonSchemaGenerator\Attribute\Field;

final readonly class FileApplyPatchRequest
{
    public function __construct(
        #[Field(
            description: 'Path to the file to patch, relative to project root',
        )]
        public string $path,
        #[Field(
            description: 'Content of the git patch to apply. It must start with "diff --git a/b.txt b/b.txt ...". In other words, it must be a valid git patch format.',
        )]
        public string $patch,
    ) {}
}