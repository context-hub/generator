<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Server;

use Butschster\ContextGenerator\McpServer\Config\McpConfig;
use Butschster\ContextGenerator\McpServer\Server\Driver\HttpServerDriver;
use Butschster\ContextGenerator\McpServer\Server\Driver\ServerDriverInterface;
use Butschster\ContextGenerator\McpServer\Server\Driver\StdioServerDriver;
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
        if ($this->config->isHttpTransport()) {
            return new HttpServerDriver(
                httpConfig: $this->config->getHttpTransportConfig(),
                logger: $this->logger,
            );
        }

        return new StdioServerDriver(
            logger: $this->logger,
        );
    }
}
