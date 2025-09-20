<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Server;

use Butschster\ContextGenerator\McpServer\Config\McpConfig;
use Butschster\ContextGenerator\McpServer\Server\Driver\ServerDriverInterface;
use Butschster\ContextGenerator\McpServer\Server\Driver\StdioServerDriver;
use Butschster\ContextGenerator\McpServer\Server\Driver\SwooleServerDriver;
use Psr\Log\LoggerInterface;
use Spiral\Exceptions\ExceptionReporterInterface;

final readonly class ServerDriverFactory
{
    public function __construct(
        private McpConfig $config,
        private LoggerInterface $logger,
        private ExceptionReporterInterface $reporter,
    ) {}

    /**
     * Create the appropriate driver based on transport configuration
     */
    public function create(): ServerDriverInterface
    {
        return match ($this->config->getTransportType()) {
            'http' => new SwooleServerDriver(
                config: $this->config->getSwooleTransportConfig(),
                logger: $this->logger,
                reporter: $this->reporter,
                messageParser: new MessageParser(),
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
