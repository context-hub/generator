<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Server;

use Butschster\ContextGenerator\McpServer\Config\McpConfig;
use Butschster\ContextGenerator\McpServer\Server\Driver\HttpServerDriver;
use Butschster\ContextGenerator\McpServer\Server\Driver\ServerDriverInterface;
use Butschster\ContextGenerator\McpServer\Server\Driver\StdioServerDriver;
use Butschster\ContextGenerator\McpServer\Server\Driver\SwooleServerDriver;
use Psr\Log\LoggerInterface;

final readonly class ServerDriverFactory
{
    public function __construct(
        private McpConfig $config,
        private LoggerInterface $logger,
    ) {}

    /**
     * Create the appropriate driver based on transport configuration
     */
    public function create(): ServerDriverInterface
    {
        return match ($this->config->getTransportType()) {
            'http' => new HttpServerDriver(
                httpConfig: $this->config->getHttpTransportConfig(),
                logger: $this->logger,
            ),
            'swoole' => new SwooleServerDriver(
                config: $this->config->getSwooleTransportConfig(),
                logger: $this->logger,
            ),
            'stdio' => new StdioServerDriver(
                logger: $this->logger,
            ),
            default => throw new \InvalidArgumentException(
                'Unsupported transport type: ' . $this->config->getTransportType(),
            ),
        };
    }
}
