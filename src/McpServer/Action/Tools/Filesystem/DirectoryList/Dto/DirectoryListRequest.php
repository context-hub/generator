<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\DirectoryList\Dto;

use Butschster\ContextGenerator\McpServer\Project\ProjectAwareRequest;
use Spiral\JsonSchemaGenerator\Attribute\Constraint\Enum;
use Spiral\JsonSchemaGenerator\Attribute\Constraint\Range;
use Spiral\JsonSchemaGenerator\Attribute\Field;

final readonly class DirectoryListRequest implements ProjectAwareRequest
{
    public function __construct(
        #[Field(
            description: 'Base directory path to list (relative to project root)',
        )]
        public string $path,
        #[Field(
            description: 'File name pattern(s) to match (e.g., "*.php", comma-separated for multiple patterns)',
        )]
        public ?string $pattern = null,
        #[Field(
            description: 'Maximum directory depth to search (0 means only files in the given directory) [default: 0]',
            default: 0,
        )]
        #[Range(min: 0)]
        public int $depth = 0,
        #[Field(
            description: 'Size filter expression (e.g., "> 1K", "< 10M", ">=1K <=10K")',
        )]
        public ?string $size = null,
        #[Field(
            description: 'Date filter expression (e.g., "since yesterday", "> 2023-01-01", "< now - 2 hours")',
        )]
        public ?string $date = null,
        #[Field(
            description: 'Only files containing this text (uses grep-like behavior)',
        )]
        public ?string $contains = null,
        #[Field(
            description: 'Filter by type: "file", "directory", or "any" (default)',
            default: 'any',
        )]
        #[Enum(values: ['file', 'directory', 'any'])]
        public string $type = 'any',
        #[Field(
            description: 'How to sort results: "name", "type", "date", "size"',
            default: 'name',
        )]
        #[Enum(values: ['name', 'type', 'date', 'size'])]
        public string $sort = 'name',
        #[Field(
            description: 'Maximum number of results to return (0 = unlimited)',
            default: 500,
        )]
        #[Range(min: 0, max: 10000)]
        public int $maxResults = 500,
        #[Field(
            description: 'Whether to include visual ASCII tree in the response',
            default: false,
        )]
        public bool $showTree = false,
        #[Field(
            description: 'Configuration options for tree view visualization',
        )]
        public ?TreeViewConfig $treeView = null,
        #[Field(
            description: 'Project identifier if multiple projects are supported. Optional.',
        )]
        public ?string $project = null,
    ) {}

    public function getProject(): ?string
    {
        return $this->project;
    }
}
