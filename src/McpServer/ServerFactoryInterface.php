<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer;

interface ServerFactoryInterface
{
    /**
     * Create a new McpServer instance with attribute-based routing
     */
    public function create(): Server;
}
