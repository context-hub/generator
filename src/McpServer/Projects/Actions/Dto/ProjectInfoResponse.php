<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Projects\Actions\Dto;

/**
 * Represents a single project in the response
 */
final readonly class ProjectInfoResponse implements \JsonSerializable
{
    /**
     * @param string[] $aliases
     */
    public function __construct(
        public string $path,
        public ?string $configFile,
        public ?string $envFile,
        public string $addedAt,
        public array $aliases,
        public bool $isCurrent,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'path' => $this->path,
            'config_file' => $this->configFile,
            'env_file' => $this->envFile,
            'added_at' => $this->addedAt,
            'aliases' => $this->aliases,
            'is_current' => $this->isCurrent,
        ];
    }
}
