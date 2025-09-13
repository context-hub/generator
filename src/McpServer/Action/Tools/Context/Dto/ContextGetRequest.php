<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Context\Dto;

use Spiral\JsonSchemaGenerator\Attribute\Field;

final readonly class ContextGetRequest
{
    public function __construct(
        #[Field(
            description: 'Output path to the context document provided in the config.',
        )]
        public string $path,
    ) {}
}