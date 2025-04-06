<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool\Types;

use Butschster\ContextGenerator\McpServer\Tool\Config\ToolDefinition;

/**
 * Interface for tool type handlers.
 */
interface ToolHandlerInterface
{
    /**
     * Checks if this handler supports the given tool type.
     */
    public function supports(string $type): bool;

    /**
     * Executes the tool with the given definition.
     *
     * @return array<string, mixed> Execution result
     * @throws \Throwable If execution fails
     */
    public function execute(ToolDefinition $tool): array;
}
