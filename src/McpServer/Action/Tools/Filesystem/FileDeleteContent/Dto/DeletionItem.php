<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileDeleteContent\Dto;

use Spiral\JsonSchemaGenerator\Attribute\Field;

/**
 * Represents a single deletion operation - either a single line or a range.
 */
final readonly class DeletionItem
{
    public function __construct(
        #[Field(
            description: '1-based line number for single line deletion, or start of range.',
        )]
        public int $line,
        #[Field(
            description: 'Optional end line for range deletion. If null, only the single line is deleted. When specified, all lines from `line` to `to` (inclusive) will be deleted.',
        )]
        public ?int $to = null,
    ) {}

    /**
     * Get all line numbers this item represents.
     *
     * @return int[]
     */
    public function getLineNumbers(): array
    {
        if ($this->to === null) {
            return [$this->line];
        }

        $start = \min($this->line, $this->to);
        $end = \max($this->line, $this->to);

        return \range($start, $end);
    }
}
