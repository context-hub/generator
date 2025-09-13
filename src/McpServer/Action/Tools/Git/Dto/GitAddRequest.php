<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Git\Dto;

use Spiral\JsonSchemaGenerator\Attribute\Field;

final readonly class GitAddRequest
{
    public function __construct(
        #[Field(
            description: 'Files or directories to stage. Use "." for all files, or provide specific paths',
        )]
        /** @var string[] */
        public array $paths = ['.'],
        #[Field(
            description: 'Stage all changes including deletions (equivalent to git add -A)',
            default: false,
        )]
        public bool $all = false,
        #[Field(
            description: 'Stage modified and new files, but not deletions (equivalent to git add .)',
            default: true,
        )]
        public bool $addNew = true,
    ) {}
}
