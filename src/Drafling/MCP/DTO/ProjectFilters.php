<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\MCP\DTO;

/**
 * Filters for project listing operations
 */
final readonly class ProjectFilters
{
    /**
     * @param string|null $status Filter by project status
     * @param string|null $template Filter by template key
     * @param string[]|null $tags Filter by tags (any of these tags)
     * @param int|null $limit Maximum number of results
     * @param int $offset Offset for pagination
     */
    public function __construct(
        public ?string $status = null,
        public ?string $template = null,
        public ?array $tags = null,
        public ?int $limit = null,
        public int $offset = 0,
    ) {}

    /**
     * Convert to array for repository filtering
     */
    public function toArray(): array
    {
        $filters = [];
        
        if ($this->status !== null) {
            $filters['status'] = $this->status;
        }
        
        if ($this->template !== null) {
            $filters['template'] = $this->template;
        }
        
        if ($this->tags !== null && !empty($this->tags)) {
            $filters['tags'] = $this->tags;
        }
        
        if ($this->limit !== null) {
            $filters['limit'] = $this->limit;
        }
        
        if ($this->offset > 0) {
            $filters['offset'] = $this->offset;
        }
        
        return $filters;
    }
}
