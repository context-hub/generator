<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileDeleteContent\Dto;

use Butschster\ContextGenerator\McpServer\Project\ProjectAwareRequest;
use Spiral\JsonSchemaGenerator\Attribute\Field;

final readonly class FileDeleteContentRequest implements ProjectAwareRequest
{
    /**
     * @param DeletionItem[] $lines
     */
    public function __construct(
        #[Field(
            description: 'Path to the file, relative to project root. Only files within project directory can be accessed.',
        )]
        public string $path,
        #[Field(
            description: 'Lines to delete. Each item can specify a single line or a range. Examples: [{"line": 5}, {"line": 10}] for individual lines, or [{"line": 5, "to": 10}] for range deletion, or mixed: [{"line": 3}, {"line": 10, "to": 15}, {"line": 20}].',
        )]
        public array $lines,
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
     * Parse and normalize line specifications into a flat array of line numbers.
     *
     * @return int[]
     */
    public function getLineNumbers(): array
    {
        $lineNumbers = [];

        foreach ($this->lines as $item) {
            foreach ($item->getLineNumbers() as $lineNumber) {
                $lineNumbers[] = $lineNumber;
            }
        }

        // Remove duplicates and sort
        $lineNumbers = \array_unique($lineNumbers);
        \sort($lineNumbers);

        return $lineNumbers;
    }
}
