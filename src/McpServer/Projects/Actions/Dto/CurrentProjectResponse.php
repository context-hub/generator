<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Projects\Actions\Dto;

/**
 * Represents current project information in response
 */
final readonly class CurrentProjectResponse implements \JsonSerializable
{
    /**
     * @param string[] $aliases
     */
    public function __construct(
        public string $path,
        public ?string $configFile,
        public ?string $envFile,
        public array $aliases,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'path' => $this->path,
            'config_file' => $this->configFile,
            'env_file' => $this->envFile,
            'aliases' => $this->aliases,
        ];
    }
}
