<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Projects\DTO;

use Butschster\ContextGenerator\Application\FSPath;

/**
 * Represents the currently active project
 */
final readonly class CurrentProjectDTO implements \JsonSerializable
{
    /**
     * @param string $path Absolute path to the project directory
     * @param string|null $configFile Path to custom configuration file within the project
     * @param string|null $envFile Path to .env file within the project
     */
    public function __construct(
        public string $path,
        private ?string $configFile = null,
        private ?string $envFile = null,
    ) {}

    /**
     * Create from array data
     */
    public static function fromArray(?array $data): ?self
    {
        if ($data === null || empty($data['path'])) {
            return null;
        }

        return new self(
            path: $data['path'],
            configFile: $data['config_file'] ?? null,
            envFile: $data['env_file'] ?? null,
        );
    }

    public function hasConfigFile(): bool
    {
        return $this->configFile !== null;
    }

    public function hasEnvFile(): bool
    {
        return $this->envFile !== null;
    }

    /**
     * Get the configuration file path
     */
    public function getConfigFile(): ?string
    {
        if ($this->configFile === null) {
            return null;
        }

        return (string) FSPath::create($this->path)->join($this->configFile);
    }

    /**
     * Get the .env file path
     */
    public function getEnvFile(): ?string
    {
        if ($this->envFile === null) {
            return null;
        }

        return (string) FSPath::create($this->path)->join($this->envFile);
    }

    /**
     * Convert to array representation
     */
    public function jsonSerialize(): array
    {
        return [
            'path' => $this->path,
            'config_file' => $this->configFile,
            'env_file' => $this->envFile,
        ];
    }
}
