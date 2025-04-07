<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool\Config;

/**
 * Represents a tool definition loaded from configuration.
 */
final readonly class ToolDefinition implements \JsonSerializable
{
    /**
     * @param string $id Unique identifier for the tool
     * @param string $description Human-readable description
     * @param array<ToolCommand> $commands List of commands to execute
     * @param ToolSchema|null $schema JSON schema for tool arguments
     * @param array<string, string> $env Environment variables for all commands
     */
    public function __construct(
        public string $id,
        public string $description,
        public array $commands,
        public ?ToolSchema $schema = null,
        public array $env = [],
    ) {}

    /**
     * Creates a ToolDefinition from a configuration array.
     *
     * @param array<string, mixed> $config The tool configuration
     * @throws \InvalidArgumentException If the configuration is invalid
     */
    public static function fromArray(array $config): self
    {
        // Validate required fields
        if (empty($config['id']) || !\is_string($config['id'])) {
            throw new \InvalidArgumentException('Tool must have a non-empty id');
        }

        if (empty($config['description']) || !\is_string($config['description'])) {
            throw new \InvalidArgumentException('Tool must have a non-empty description');
        }

        // Validate and parse commands
        $commands = [];
        if (!isset($config['commands']) || !\is_array($config['commands'])) {
            throw new \InvalidArgumentException('Tool must have a non-empty commands array');
        }

        foreach ($config['commands'] as $index => $commandConfig) {
            if (!\is_array($commandConfig)) {
                throw new \InvalidArgumentException(
                    \sprintf('Command at index %d must be an array', $index),
                );
            }

            try {
                $commands[] = ToolCommand::fromArray($commandConfig, $config['workingDir'] ?? null);
            } catch (\InvalidArgumentException $e) {
                throw new \InvalidArgumentException(
                    \sprintf('Invalid command at index %d: %s', $index, $e->getMessage()),
                    previous: $e,
                );
            }
        }

        $env = [];
        if (isset($config['env']) && \is_array($config['env'])) {
            foreach ($config['env'] as $key => $value) {
                if (!\is_string($key) || !\is_string($value)) {
                    throw new \InvalidArgumentException('Environment variables must be string key-value pairs');
                }
                $env[$key] = $value;
            }
        }

        return new self(
            id: $config['id'],
            description: $config['description'],
            commands: $commands,
            schema: ToolSchema::fromArray($config['schema'] ?? []),
            env: $env,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return \array_filter([
            'id' => $this->id,
            'description' => $this->description,
            'commands' => $this->commands,
            'schema' => $this->schema,
            'env' => $this->env,
        ], static fn($value) => $value !== null && $value !== []);
    }
}
