<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\MCP\Tools\RagStore\Dto;

use Spiral\JsonSchemaGenerator\Attribute\Field;

final readonly class RagStoreRequest
{
    public function __construct(
        #[Field(description: 'Content to store in the knowledge base')]
        public string $content,
        #[Field(description: 'Type: architecture, api, testing, convention, tutorial, reference, general')]
        public string $type = 'general',
        #[Field(description: 'Source path (e.g., "src/Auth/Service.php")')]
        public ?string $sourcePath = null,
        #[Field(description: 'Tags (comma-separated)')]
        public ?string $tags = null,
    ) {}

    public function getParsedTags(): ?array
    {
        if ($this->tags === null || \trim($this->tags) === '') {
            return null;
        }

        return \array_map(\trim(...), \explode(',', $this->tags));
    }
}
