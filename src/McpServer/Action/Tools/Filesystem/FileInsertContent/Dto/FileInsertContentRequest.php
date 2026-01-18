<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileInsertContent\Dto;

use Butschster\ContextGenerator\McpServer\Project\ProjectAwareRequest;
use Spiral\JsonSchemaGenerator\Attribute\Field;

final readonly class FileInsertContentRequest implements ProjectAwareRequest
{
    /**
     * @param InsertionItem[] $insertions
     */
    public function __construct(
        #[Field(
            description: 'Path to the file, relative to project root. Only files within project directory can be accessed.',
        )]
        public string $path,
        #[Field(
            description: 'Array of insertions. Each insertion must have "line" (1-based line number, or -1 for end of file) and "content" (string to insert). Example: [{"line": 5, "content": "use App\\Service;"}]',
        )]
        public array $insertions,
        #[Field(
            description: 'Insert position relative to the specified line. Use "before" to insert before the line, "after" to insert after the line.',
            default: 'after',
        )]
        public string $position = 'after',
        #[Field(
            description: 'Project identifier if multiple projects are supported. Optional.',
        )]
        public ?string $project = null,
    ) {}

    public function getProject(): ?string
    {
        return $this->project;
    }

    /**
     * Get insertion items.
     *
     * @return InsertionItem[]
     */
    public function getInsertionItems(): array
    {
        return $this->insertions;
    }
}
