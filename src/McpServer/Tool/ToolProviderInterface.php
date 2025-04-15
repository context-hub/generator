<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool;

use Butschster\ContextGenerator\McpServer\Tool\Config\ToolDefinition;

interface ToolProviderInterface
{
    /**
     * Checks if a tool with the given ID exists.
     */
    public function has(string $id): bool;

    /**
     * Gets a tool by ID.
     *
     * @throws \InvalidArgumentException If no tool with the given ID exists
     */
    public function get(string $id): ToolDefinition;

    /**
     * Gets all tools.
     *
     * @return list<ToolDefinition>
     */
    public function all(): array;
}
