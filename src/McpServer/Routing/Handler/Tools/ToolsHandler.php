<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Routing\Handler\Tools;

use Butschster\ContextGenerator\McpServer\Routing\Handler\BaseHandler;
use Laminas\Diactoros\Response\JsonResponse;
use Mcp\Types\CallToolRequestParams;
use Mcp\Types\CallToolResult;
use Mcp\Types\ListToolsResult;
use Mcp\Types\TextContent;

/**
 * Handler for tool-related MCP requests
 */
final readonly class ToolsHandler extends BaseHandler implements ToolsHandlerInterface
{
    #[\Override]
    public function handleToolsList(): ListToolsResult
    {
        try {
            return $this->handleRoute('tools/list');
        } catch (\Throwable $e) {
            $this->logError('Tools list', 'tools/list', $e);
            return new ListToolsResult([]);
        }
    }

    #[\Override]
    public function handleToolsCall(CallToolRequestParams $params): CallToolResult
    {
        $params = $this->projectService->processToolRequestParams($params);

        $method = 'tools/call/' . $params->name;
        $arguments = $params->arguments ?? [];

        $this->logger->debug('Handling tool call', [
            'tool' => $params->name,
            'method' => $method,
            'arguments' => $arguments,
        ]);

        try {
            // Create PSR request with the tool name in the path and arguments as POST body
            $request = $this->requestAdapter->createPsrRequest($method, $arguments);

            $response = $this->router->dispatch($request);
            \assert($response instanceof JsonResponse);

            return $response->getPayload();
        } catch (\Throwable $e) {
            $this->logError('Tool call', $method, $e);
            return new CallToolResult([new TextContent(text: $e->getMessage())], isError: true);
        }
    }
}
