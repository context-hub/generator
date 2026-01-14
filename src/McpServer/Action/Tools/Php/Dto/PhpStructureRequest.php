<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Php\Dto;

use Butschster\ContextGenerator\McpServer\Project\ProjectAwareRequest;
use Spiral\JsonSchemaGenerator\Attribute\Constraint\Range;
use Spiral\JsonSchemaGenerator\Attribute\Field;

final readonly class PhpStructureRequest implements ProjectAwareRequest
{
    public function __construct(
        #[Field(
            description: 'Path to PHP file, relative to project root',
        )]
        public string $path,
        #[Field(
            description: 'How deep to follow relationships (0 = only requested file, 1-3 = follow local links)',
            default: 1,
        )]
        #[Range(min: 0, max: 3)]
        public int $depth = 1,
        #[Field(
            description: 'Include private and protected members in output',
            default: false,
        )]
        public bool $showPrivate = false,
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
