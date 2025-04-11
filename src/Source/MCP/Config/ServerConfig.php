<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\MCP\Config;

use Butschster\ContextGenerator\Lib\Variable\VariableResolver;

/**
 * Configuration for an MCP server
 */
final readonly class ServerConfig implements \JsonSerializable
{
    private function __construct(
        public ?string $command,
        public array $args,
        public array $env,
        public ?string $url,
        public array $headers,
    ) {}

    /**
     * Create a new server configuration for a command-based server
     */
    public static function createForCommand(
        string $command,
        array $args = [],
        array $env = [],
    ): self {
        return new self(
            command: $command,
            args: $args,
            env: $env,
            url: null,
            headers: [],
        );
    }

    /**
     * Create a new server configuration for a URL-based server
     *
     * @param string $url Server URL
     * @param array<string, string> $headers HTTP headers
     */
    public static function createForUrl(
        string $url,
        array $headers = [],
    ): self {
        return new self(
            command: null,
            args: [],
            env: [],
            url: $url,
            headers: $headers,
        );
    }

    /**
     * Create a server configuration from an array
     */
    public static function fromArray(array $config): self
    {
        if (isset($config['url'])) {
            return self::createForUrl(
                url: (string) $config['url'],
                headers: (array) ($config['headers'] ?? []),
            );
        }

        if (isset($config['command'])) {
            return self::createForCommand(
                command: (string) $config['command'],
                args: (array) ($config['args'] ?? []),
                env: (array) ($config['env'] ?? []),
            );
        }

        throw new \InvalidArgumentException('Server configuration must have either "url" or "command" property');
    }

    /**
     * Apply variable resolution to this configuration
     */
    public function withResolvedVariables(VariableResolver $resolver): self
    {
        // For command-based servers
        if ($this->command !== null) {
            $resolvedEnv = [];
            foreach ($this->env as $key => $value) {
                $resolvedEnv[$key] = $resolver->resolve($value);
            }

            return new self(
                command: $resolver->resolve($this->command),
                args: \array_map($resolver->resolve(...), $this->args),
                env: $resolvedEnv,
                url: null,
                headers: [],
            );
        }

        // For URL-based servers
        if ($this->url !== null) {
            $resolvedHeaders = [];
            foreach ($this->headers as $key => $value) {
                $resolvedHeaders[$key] = $resolver->resolve($value);
            }

            return new self(
                command: null,
                args: [],
                env: [],
                url: $resolver->resolve($this->url),
                headers: $resolvedHeaders,
            );
        }

        return $this;
    }

    public function isCommandBased(): bool
    {
        return $this->command !== null;
    }

    public function isUrlBased(): bool
    {
        return $this->url !== null;
    }

    public function jsonSerialize(): array
    {
        if ($this->isCommandBased()) {
            return \array_filter([
                'command' => $this->command,
                'args' => $this->args,
                'env' => $this->env,
            ]);
        }

        if ($this->isUrlBased()) {
            return \array_filter([
                'url' => $this->url,
                'headers' => $this->headers,
            ]);
        }

        return [];
    }
}
