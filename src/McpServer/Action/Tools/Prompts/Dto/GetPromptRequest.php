<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Prompts\Dto;

use Spiral\JsonSchemaGenerator\Attribute\Field;

final readonly class GetPromptRequest
{
    public function __construct(
        #[Field(
            description: 'The ID of the prompt to retrieve. You can find valid prompt IDs by first using the prompts-list tool.',
        )]
        public string $id,
    ) {}
}
