<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Domain\Model;

/**
 * Project represents an instance of a template with its own configuration and entries
 */
final readonly class Project implements \JsonSerializable
{
    /**
     * @param string $id Unique project identifier
     * @param string $name Project name
     * @param string $description Project description
     * @param string $template Template key this project is based on
     * @param string $status Project status
     * @param string[] $tags Project tags for organization
     * @param string[] $entryDirs Directories to scan for entries
     * @param string[] $memory LLM memory entries for project context
     * @param string|null $projectPath Optional file path for storage reference
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public string $template,
        public string $status,
        public array $tags,
        public array $entryDirs,
        public array $memory = [],
        public ?string $projectPath = null,
    ) {}

    /**
     * Create updated project with new values
     */
    public function withUpdates(
        ?string $name = null,
        ?string $description = null,
        ?string $status = null,
        ?array $tags = null,
        ?array $entryDirs = null,
        ?array $memory = null,
    ): self {
        return new self(
            id: $this->id,
            name: $name ?? $this->name,
            description: $description ?? $this->description,
            template: $this->template,
            status: $status ?? $this->status,
            tags: $tags ?? $this->tags,
            entryDirs: $entryDirs ?? $this->entryDirs,
            memory: $memory ?? $this->memory,
            projectPath: $this->projectPath,
        );
    }

    /**
     * Create project with added memory entry
     */
    public function withAddedMemory(string $memoryEntry): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            description: $this->description,
            template: $this->template,
            status: $this->status,
            tags: $this->tags,
            entryDirs: $this->entryDirs,
            memory: [...$this->memory, $memoryEntry],
            projectPath: $this->projectPath,
        );
    }

    /**
     * Generate directory name for this project
     */
    public function generateDirectoryName(): string
    {
        $slug = \preg_replace('/[^a-z0-9]+/', '-', \strtolower($this->name));
        return \trim((string) $slug, '-');
    }

    /**
     * Get project configuration as array
     */
    public function getConfiguration(): array
    {
        return [
            'project' => [
                'name' => $this->name,
                'description' => $this->description,
                'template' => $this->template,
                'status' => $this->status,
                'tags' => $this->tags,
                'memory' => $this->memory,
                'entries' => [
                    'dirs' => $this->entryDirs,
                ],
            ],
        ];
    }

    /**
     * Specify data which should be serialized to JSON
     */
    public function jsonSerialize(): array
    {
        return [
            'project_id' => $this->id,
            'title' => $this->name,
            'status' => $this->status,
            'project_type' => $this->template,
            'created_at' => (new \DateTime())->format('c'), // Would need actual creation date from domain
            'updated_at' => (new \DateTime())->format('c'), // Would need actual update date from domain
            'metadata' => [
                'description' => $this->description,
                'tags' => $this->tags,
                'entry_dirs' => $this->entryDirs,
                'memory' => $this->memory,
            ],
        ];
    }
}
