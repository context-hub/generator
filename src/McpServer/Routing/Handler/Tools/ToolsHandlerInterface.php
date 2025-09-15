<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Routing\Handler\Tools;

use Mcp\Types\CallToolRequestParams;
use Mcp\Types\CallToolResult;
use Mcp\Types\ListToolsResult;

/**
 * Interface for handling tool-related MCP requests
 */
interface ToolsHandlerInterface
{
    /**
     * Handle tools/list request
     */
    public function handleToolsList(): ListToolsResult;

    /**
     * Handle tools/call request
     */
    public function handleToolsCall(CallToolRequestParams $params): CallToolResult;
}
