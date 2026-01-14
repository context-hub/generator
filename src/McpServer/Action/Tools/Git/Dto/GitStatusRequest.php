<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Git\Dto;

use Butschster\ContextGenerator\McpServer\Project\ProjectAwareRequest;
use Spiral\JsonSchemaGenerator\Attribute\Field;

final readonly class GitStatusRequest implements ProjectAwareRequest
{
    public function __construct(
        #[Field(
            description: 'Format of the status output: short, porcelain, or long',
            default: 'short',
        )]
        public GitStatusFormat $format = GitStatusFormat::Short,
        #[Field(
            description: 'Show untracked files in status output',
            default: true,
        )]
        public bool $showUntracked = true,
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
