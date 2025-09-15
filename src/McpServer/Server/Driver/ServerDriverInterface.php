<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Server\Driver;

use Mcp\Server\InitializationOptions;
use Mcp\Server\Server as McpServer;

interface ServerDriverInterface
{
    /**
     * Run the server with the specified configuration
     */
    public function run(McpServer $server, InitializationOptions $initOptions): void;
}
