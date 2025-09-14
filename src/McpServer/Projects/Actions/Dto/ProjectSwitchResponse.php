<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Projects\Actions\Dto;

/**
 * Response for project switch tool
 */
final readonly class ProjectSwitchResponse implements \JsonSerializable
{
    public function __construct(
        public bool $success,
        public string $message,
        public ?CurrentProjectResponse $currentProject = null,
        public ?AliasResolutionResponse $resolvedFromAlias = null,
    ) {}

    public function jsonSerialize(): array
    {
        return \array_filter([
            'success' => $this->success,
            'message' => $this->message,
            'current_project' => $this->currentProject,
            'resolved_from_alias' => $this->resolvedFromAlias,
        ], static fn($value) => $value !== null);
    }
}
