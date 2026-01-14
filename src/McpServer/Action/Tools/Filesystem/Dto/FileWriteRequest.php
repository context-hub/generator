<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\Dto;

use Butschster\ContextGenerator\McpServer\Project\ProjectAwareRequest;
use Spiral\JsonSchemaGenerator\Attribute\Field;

final readonly class FileWriteRequest implements ProjectAwareRequest
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
