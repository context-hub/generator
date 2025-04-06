<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool;

use Butschster\ContextGenerator\McpServer\Tool\Config\ToolDefinition;

interface ToolRegistryInterface
{
    /**
     * Registers a tool in the registry.
     *
     * @throws \InvalidArgumentException If a tool with the same ID already exists
     */
    public function register(ToolDefinition $tool): void;
}
