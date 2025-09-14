<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Projects\Actions\Dto;

use Spiral\JsonSchemaGenerator\Attribute\Field;

final readonly class ProjectSwitchRequest
{
    public function __construct(
        #[Field(
            description: 'Alias to the project to switch to. Should be project alias.',
        )]
        public string $alias,
    ) {}
}
