<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Context\Dto;

use Spiral\JsonSchemaGenerator\Attribute\Field;

final readonly class ContextRequestRequest
{
    public function __construct(
        #[Field(
            description: 'Context configuration in JSON format. It should contain the context documents with sources (file, tree, text) and any filters or modifiers to be applied.',
        )]
        public string $json,
    ) {}
}
