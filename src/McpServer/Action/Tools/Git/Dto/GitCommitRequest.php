<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Git\Dto;

use Butschster\ContextGenerator\McpServer\Project\ProjectAwareRequest;
use Spiral\JsonSchemaGenerator\Attribute\Field;

final readonly class GitCommitRequest implements ProjectAwareRequest
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
        #[Field(
            description: 'Project identifier if multiple projects are supported. Optional.',
        )]
        public ?string $project = null,
    ) {}

    public function getProject(): ?string
    {
        return $this->project;
    }
}
