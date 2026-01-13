<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileRead\Dto;

use Spiral\JsonSchemaGenerator\Attribute\Field;

final readonly class FileReadRequest
{
    /**
     * @param string|null $path Single file path (backward compatible)
     * @param string[]|null $paths Array of file paths for batch reading
     * @param string $encoding File encoding
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
}
