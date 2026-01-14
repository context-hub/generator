<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Projects\Actions\Dto;

use Butschster\ContextGenerator\McpServer\Project\ProjectConfig;

/**
 * Response for projects list tool
 */
final readonly class ProjectsListResponse implements \JsonSerializable
{
    /**
     * @param ProjectConfig[] $projects
     */
    public function __construct(
        public array $projects,
        public ?CurrentProjectResponse $currentProject,
        public int $totalProjects,
        public ?string $message = null,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'projects' => $this->projects,
            'current_project' => $this->currentProject,
            'total_projects' => $this->totalProjects,
            'message' => $this->message,
        ];
    }
}
