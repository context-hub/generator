<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool\Config;

/**
 * Represents a command to be executed by a tool.
 */
final readonly class ToolCommand implements \JsonSerializable
{
    /**
     * @param string $cmd The command to execute
     * @param array<ToolArg> $args Command arguments
     * @param string|null $workingDir Optional working directory (relative to project root)
     * @param array<string, string> $env Optional environment variables
     */
    public function __construct(
        public string $cmd,
        public array $args = [],
        public ?string $workingDir = null,
        public array $env = [],
    ) {}

    /**
     * Creates a ToolCommand from a configuration array.
     *
     * @param array<string, mixed> $config The command configuration
     * @throws \InvalidArgumentException If the configuration is invalid
     */
    public static function fromArray(array $config, ?string $workingDir = null): self
    {
        if (!isset($config['cmd']) || !\is_string($config['cmd'])) {
            throw new \InvalidArgumentException('Command must have a non-empty "cmd" property');
        }

        $args = [];
        if (isset($config['args'])) {
            if (!\is_array($config['args'])) {
                throw new \InvalidArgumentException('Command "args" must be an array');
            }

            foreach ($config['args'] as $argConfig) {
                try {
                    $args[] = ToolArg::fromMixed($argConfig);
                } catch (\InvalidArgumentException $e) {
                    throw new \InvalidArgumentException(
                        'Invalid argument configuration: ' . $e->getMessage(),
                        previous: $e,
                    );
                }
            }
        }

        if (
            isset($config['workingDir'])
            && $config['workingDir'] !== '.'
            && $config['workingDir'] !== ''
            && $config['workingDir'] !== null
        ) {
            if (!\is_string($config['workingDir'])) {
                throw new \InvalidArgumentException('Command "workingDir" must be a string');
            }
            $workingDir = $config['workingDir'];
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
            cmd: $config['cmd'],
            args: $args,
            workingDir: $workingDir,
            env: $env,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $argsArray = [];

        foreach ($this->args as $arg) {
            if ($arg->when === null) {
                // Simple argument
                $argsArray[] = $arg->name;
            } else {
                // Conditional argument
                $argsArray[] = $arg->toArray();
            }
        }

        return \array_filter([
            'cmd' => $this->cmd,
            'args' => $argsArray,
            'workingDir' => $this->workingDir,
            'env' => $this->env,
        ], static fn($value) => $value !== null && $value !== []);
    }
}
