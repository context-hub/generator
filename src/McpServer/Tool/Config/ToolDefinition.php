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
     * @param string $type Tool type (default: 'run')
     * @param list<ToolCommand> $commands List of commands to execute (for 'run' type)
     * @param ToolSchema|null $schema JSON schema for tool arguments
     * @param array<string, string> $env Environment variables for all commands
     * @param array<string, mixed> $extra Additional type-specific configuration data
     */
    public function __construct(
        public string $id,
        public string $description,
        public string $type = 'run',
        public array $commands = [],
        public ?ToolSchema $schema = null,
        public array $env = [],
        public array $extra = [],
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

        // Get tool type
        $type = $config['type'] ?? 'run';

        // Extract any extra configuration data (type-specific)
        $extra = [];
        $reservedKeys = ['id', 'description', 'type', 'commands', 'schema', 'env', 'workingDir'];
        foreach ($config as $key => $value) {
            if (!\in_array($key, $reservedKeys, true)) {
                $extra[$key] = $value;
            }
        }

        // For 'run' type, validate and parse commands
        $commands = [];
        if ($type === 'run') {
            if (!isset($config['commands']) || !\is_array($config['commands'])) {
                throw new \InvalidArgumentException('Run-type tool must have a non-empty commands array');
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
        } elseif (isset($config['commands']) && \is_array($config['commands'])) {
            // For non-run types, store commands in extra if provided
            $extra['commands'] = $config['commands'];
        }

        // Handle 'http' type specific validations
        if ($type === 'http') {
            if (!isset($config['requests']) || !\is_array($config['requests']) || empty($config['requests'])) {
                throw new \InvalidArgumentException('HTTP tool must have a non-empty requests array');
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
            type: $type,
            commands: $commands,
            schema: ToolSchema::fromArray($config['schema'] ?? []),
            env: $env,
            extra: $extra,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $result = [
            'id' => $this->id,
            'description' => $this->description,
            'type' => $this->type,
        ];

        if ($this->type === 'run' && !empty($this->commands)) {
            $result['commands'] = $this->commands;
        }

        if ($this->schema !== null) {
            $result['schema'] = $this->schema;
        }

        if (!empty($this->env)) {
            $result['env'] = $this->env;
        }

        // Include any extra type-specific data
        foreach ($this->extra as $key => $value) {
            $result[$key] = $value;
        }

        return $result;
    }
}
