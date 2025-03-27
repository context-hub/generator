<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Routing;

use Laminas\Diactoros\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;

final readonly class Mcp2PsrRequestAdapter
{
    /**
     * Convert MCP request parameters to PSR-7 ServerRequestInterface
     */
    public function createPsrRequest(string $method, array $mcpParams = []): ServerRequestInterface
    {
        $path = '/' . $method;

        // Default to GET method for all requests
        $httpMethod = 'GET';

        // Use POST for methods that modify state (typically tool calls)
        if (\str_starts_with($method, 'tools/call')) {
            $httpMethod = 'POST';
        }

        // Create request with parameters as attributes and/or body
        $request = new ServerRequest([], [], $path, $httpMethod);

        // Add MCP parameters as request attributes
        foreach ($mcpParams as $key => $value) {
            $request = $request->withAttribute($key, $value);
        }

        // For POST requests, also add parameters to parsed body
        if ($httpMethod === 'POST' && !empty($mcpParams)) {
            $request = $request->withParsedBody($mcpParams);
        }

        return $request;
    }
}
