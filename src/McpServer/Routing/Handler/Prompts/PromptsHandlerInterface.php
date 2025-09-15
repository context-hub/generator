<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Routing\Handler\Prompts;

use Mcp\Types\GetPromptRequestParams;
use Mcp\Types\GetPromptResult;
use Mcp\Types\ListPromptsResult;

/**
 * Interface for handling prompt-related MCP requests
 */
interface PromptsHandlerInterface
{
    /**
     * Handle prompts/list request
     */
    public function handlePromptsList(): ListPromptsResult;

    /**
     * Handle prompts/get request
     */
    public function handlePromptsGet(GetPromptRequestParams $params): GetPromptResult;
}
