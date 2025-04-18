<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Gitlab\Config;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Psr\Log\LoggerInterface;

final class ServerRegistry
{
    /** @var array<string, ServerConfig> */
    private array $servers = [];

    public function __construct(
        #[LoggerPrefix(prefix: 'gitlab-server-registry')]
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function register(string $name, ServerConfig $config): self
    {
        $this->logger?->debug('Registering GitLab server', [
            'name' => $name,
            'config' => $config,
        ]);

        $this->servers[$name] = $config;

        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->servers[$name]);
    }

    public function get(string $name): ServerConfig
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException(\sprintf('GitLab server "%s" not found in registry', $name));
        }

        return $this->servers[$name];
    }

    public function all(): array
    {
        return $this->servers;
    }

    public function getDefault(): ?ServerConfig
    {
        return $this->servers['default'] ?? null;
    }
}
