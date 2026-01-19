<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\MCP\Tools\RagManage\Dto;

use Spiral\JsonSchemaGenerator\Attribute\Field;

final readonly class RagManageRequest
{
    public function __construct(
        #[Field(description: 'Action to perform: stats')]
        public string $action = 'stats',
    ) {}
}
