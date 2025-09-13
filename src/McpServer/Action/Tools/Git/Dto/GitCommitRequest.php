<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Git\Dto;

use Spiral\JsonSchemaGenerator\Attribute\Field;

final readonly class GitCommitRequest
{
    public function __construct(
        #[Field(
            description: 'Commit message',
        )]
        public string $message,
        #[Field(
            description: 'Stage all tracked files before committing (equivalent to git commit -a)',
            default: false,
        )]
        public bool $stageAll = false,
    ) {}
}
