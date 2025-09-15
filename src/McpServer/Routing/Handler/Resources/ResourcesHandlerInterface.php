<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Routing\Handler\Resources;

use Mcp\Types\ListResourcesResult;
use Mcp\Types\ReadResourceRequestParams;
use Mcp\Types\ReadResourceResult;

/**
 * Interface for handling resource-related MCP requests
 */
interface ResourcesHandlerInterface
{
    /**
     * Handle resources/list request
     */
    public function handleResourcesList(): ListResourcesResult;

    /**
     * Handle resources/read request
     */
    public function handleResourcesRead(ReadResourceRequestParams $params): ReadResourceResult;
}
