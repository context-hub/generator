<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\MCP\Config;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Psr\Log\LoggerInterface;

/**
 * Registry for MCP server configurations
 */
final class ServerRegistry
{
    /** @var array<string, ServerConfig> */
    private array $servers = [];

    public function __construct(
        #[LoggerPrefix(prefix: 'mcp-server-registry')]
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * Register a server configuration
     */
    public function register(string $name, ServerConfig $config): self
    {
        $this->logger?->debug('Registering MCP server', [
            'name' => $name,
            'config' => $config,
        ]);

        $this->servers[$name] = $config;

        return $this;
    }

    /**
     * Check if a server with the given name exists
     */
    public function has(string $name): bool
    {
        return isset($this->servers[$name]);
    }

    /**
     * Get a server configuration by name
     */
    public function get(string $name): ServerConfig
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException(\sprintf('MCP server "%s" not found in registry', $name));
        }

        return $this->servers[$name];
    }

    /**
     * Get all server configurations
     *
     * @return array<string, ServerConfig>
     */
    public function all(): array
    {
        return $this->servers;
    }
}
