<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer;

interface ServerRunnerInterface
{
    /**
     * Create a new McpServer instance with attribute-based routing
     */
    public function run(string $name): void;
}
