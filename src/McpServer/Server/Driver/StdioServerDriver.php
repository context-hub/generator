<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Server\Driver;

use Mcp\Server\Server as McpServer;
use Mcp\Server\ServerRunner;
use Psr\Log\LoggerInterface;

final readonly class StdioServerDriver implements ServerDriverInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function run(McpServer $server, mixed $initOptions): void
    {
        $runner = new ServerRunner(
            server: $server,
            initOptions: $initOptions,
            logger: $this->logger,
        );

        // For STDIO, run continuous loop
        $runner->run();
    }
}
