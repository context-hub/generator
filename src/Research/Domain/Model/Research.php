<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\Domain\Model;

final readonly class Research implements \JsonSerializable
{
    /**
     * @param string $id Unique research identifier
     * @param string $name Research name
     * @param string $description Research description
     * @param string $template Template key this research is based on
     * @param string $status Research status
     * @param string[] $tags Research tags for organization
     * @param string[] $entryDirs Directories to scan for entries
     * @param string[] $memory LLM memory entries for research context
     * @param string|null $path Optional file path for storage reference
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
        public ?string $path = null,
    ) {}

    /**
     * Create updated research with new values
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
            path: $this->path,
        );
    }

    /**
     * Create research with added memory entry
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
            path: $this->path,
        );
    }

    /**
     * Specify data which should be serialized to JSON
     */
    public function jsonSerialize(): array
    {
        return [
            'research_id' => $this->id,
            'title' => $this->name,
            'status' => $this->status,
            'research_type' => $this->template,
            'metadata' => [
                'description' => $this->description,
                'tags' => $this->tags,
                'memory' => $this->memory,
            ],
        ];
    }
}
