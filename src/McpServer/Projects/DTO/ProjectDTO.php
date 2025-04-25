<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Projects\DTO;

/**
 * Represents a single project configuration
 */
final readonly class ProjectDTO implements \JsonSerializable
{
    /**
     * @param string $path Absolute path to the project directory
     * @param string $addedAt Date when the project was added
     * @param string|null $configFile Path to custom configuration file within the project
     * @param string|null $envFile Path to .env file within the project
     */
    public function __construct(
        public string $path,
        public string $addedAt = '',
        public ?string $configFile = null,
        public ?string $envFile = null,
    ) {}

    /**
     * Create from array data
     */
    public static function fromArray(string $path, array $data): self
    {
        return new self(
            path: $path,
            addedAt: $data['added_at'] ?? \date('Y-m-d H:i:s'),
            configFile: $data['config_file'] ?? null,
            envFile: $data['env_file'] ?? null,
        );
    }

    /**
     * Convert to array representation
     */
    public function jsonSerialize(): array
    {
        return [
            'added_at' => $this->addedAt,
            'config_file' => $this->configFile,
            'env_file' => $this->envFile,
        ];
    }
}
