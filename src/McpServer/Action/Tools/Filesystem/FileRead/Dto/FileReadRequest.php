<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileRead\Dto;

use Butschster\ContextGenerator\McpServer\Project\ProjectAwareRequest;
use Spiral\JsonSchemaGenerator\Attribute\Constraint\Range;
use Spiral\JsonSchemaGenerator\Attribute\Field;

final readonly class FileReadRequest implements ProjectAwareRequest
{
    /**
     * @param string|null $path Single file path (backward compatible)
     * @param string[]|null $paths Array of file paths for batch reading
     * @param string $encoding File encoding
     * @param int|null $startLine First line to read (1-based, inclusive). Only applies to single file requests.
     * @param int|null $endLine Last line to read (1-based, inclusive). Only applies to single file requests.
     * @param string|null $project Project identifier if multiple projects are supported
     */
    public function __construct(
        #[Field(
            description: 'Path to the file, relative to project root. Only files within project directory can be accessed.',
        )]
        public ?string $path = null,
        #[Field(
            description: 'Array of file paths to read. Use this for batch reading multiple files.',
        )]
        public ?array $paths = null,
        #[Field(
            description: 'File encoding (default: utf-8)',
            default: 'utf-8',
        )]
        public string $encoding = 'utf-8',
        #[Field(
            description: 'First line to read (1-based, inclusive). Only applies to single file requests. If omitted, reads from the beginning.',
        )]
        #[Range(min: 1)]
        public ?int $startLine = null,
        #[Field(
            description: 'Last line to read (1-based, inclusive). Only applies to single file requests. If omitted, reads to the end.',
        )]
        #[Range(min: 1)]
        public ?int $endLine = null,
        #[Field(
            description: 'Project identifier if multiple projects are supported. Optional.',
        )]
        public ?string $project = null,
    ) {}

    /**
     * Get all unique paths to read, merging path and paths if both provided.
     *
     * @return string[]
     */
    public function getAllPaths(): array
    {
        $allPaths = [];

        if ($this->path !== null && $this->path !== '') {
            $allPaths[] = $this->path;
        }

        if ($this->paths !== null) {
            foreach ($this->paths as $p) {
                if (\is_string($p) && $p !== '') {
                    $allPaths[] = $p;
                }
            }
        }

        return \array_values(\array_unique($allPaths));
    }

    /**
     * Check if this is a single file request (backward compatible mode).
     */
    public function isSingleFileRequest(): bool
    {
        return $this->path !== null
            && $this->path !== ''
            && ($this->paths === null || $this->paths === []);
    }

    public function getProject(): ?string
    {
        return $this->project;
    }

    /**
     * Check if a line range is specified.
     */
    public function hasLineRange(): bool
    {
        return $this->startLine !== null || $this->endLine !== null;
    }

    /**
     * Get the effective start line (1 if not specified).
     */
    public function getEffectiveStartLine(): int
    {
        return $this->startLine ?? 1;
    }

    /**
     * Get the effective end line (null means read to end).
     */
    public function getEffectiveEndLine(): ?int
    {
        return $this->endLine;
    }
}
