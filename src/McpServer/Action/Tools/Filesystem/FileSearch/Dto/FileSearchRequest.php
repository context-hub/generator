<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileSearch\Dto;

use Butschster\ContextGenerator\McpServer\Project\ProjectAwareRequest;
use Spiral\JsonSchemaGenerator\Attribute\Constraint\Range;
use Spiral\JsonSchemaGenerator\Attribute\Field;

final readonly class FileSearchRequest implements ProjectAwareRequest
{
    public function __construct(
        #[Field(
            description: 'Search query - text string or regex pattern to find in files',
        )]
        public string $query,
        #[Field(
            description: 'Base directory path to search (relative to project root)',
        )]
        public string $path = '',
        #[Field(
            description: 'File name pattern(s) to match (e.g., "*.php", comma-separated for multiple)',
        )]
        public ?string $pattern = null,
        #[Field(
            description: 'Maximum directory depth to search (0 = only specified directory)',
            default: 10,
        )]
        #[Range(min: 0, max: 50)]
        public int $depth = 10,
        #[Field(
            description: 'Number of context lines to show before and after each match',
            default: 2,
        )]
        #[Range(min: 0, max: 10)]
        public int $contextLines = 2,
        #[Field(
            description: 'Whether the search is case-sensitive',
            default: true,
        )]
        public bool $caseSensitive = true,
        #[Field(
            description: 'Whether to treat query as a regex pattern',
            default: false,
        )]
        public bool $regex = false,
        #[Field(
            description: 'Maximum matches to return per file (0 = unlimited)',
            default: 50,
        )]
        #[Range(min: 0, max: 1000)]
        public int $maxMatchesPerFile = 50,
        #[Field(
            description: 'Maximum total matches to return across all files (0 = unlimited)',
            default: 200,
        )]
        #[Range(min: 0, max: 5000)]
        public int $maxTotalMatches = 200,
        #[Field(
            description: 'Size filter expression (e.g., "< 1M", "> 1K")',
        )]
        public ?string $size = null,
        #[Field(
            description: 'Project identifier if multiple projects are supported',
        )]
        public ?string $project = null,
    ) {}

    public function getProject(): ?string
    {
        return $this->project;
    }

    /**
     * Build the regex pattern for searching.
     */
    public function buildPattern(): string
    {
        if ($this->regex) {
            $pattern = $this->query;
            // Ensure pattern has delimiters
            if (!\preg_match('/^[\/\#\~\@]/', $pattern)) {
                $pattern = '/' . $pattern . '/';
            }
        } else {
            // Escape special regex characters for literal search
            $pattern = '/' . \preg_quote($this->query, '/') . '/';
        }

        // Add case-insensitive modifier if needed
        if (!$this->caseSensitive && !\str_contains($pattern, 'i')) {
            $pattern .= 'i';
        }

        return $pattern;
    }
}
