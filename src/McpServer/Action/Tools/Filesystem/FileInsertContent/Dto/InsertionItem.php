<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileInsertContent\Dto;

use Spiral\JsonSchemaGenerator\Attribute\Field;

/**
 * Represents a single insertion operation with line number and content.
 */
final readonly class InsertionItem
{
    public function __construct(
        #[Field(
            description: '1-based line number where content should be inserted. Use -1 to insert at the end of the file.',
        )]
        public int $line,
        #[Field(
            description: 'Content to insert. Can be multiline (use \n for line breaks).',
        )]
        public string $content,
    ) {}
}
