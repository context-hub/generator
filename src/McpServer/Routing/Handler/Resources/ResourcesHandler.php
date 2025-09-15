<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Routing\Handler\Resources;

use Butschster\ContextGenerator\McpServer\Routing\Handler\BaseHandler;
use Laminas\Diactoros\Response\JsonResponse;
use Mcp\Types\ListResourcesResult;
use Mcp\Types\ReadResourceRequestParams;
use Mcp\Types\ReadResourceResult;

/**
 * Handler for resource-related MCP requests
 */
final readonly class ResourcesHandler extends BaseHandler implements ResourcesHandlerInterface
{
    #[\Override]
    public function handleResourcesList(): ListResourcesResult
    {
        try {
            return $this->handleRoute('resources/list');
        } catch (\Throwable $e) {
            $this->logError('Resources list', 'resources/list', $e);
            return new ListResourcesResult([]);
        }
    }

    #[\Override]
    public function handleResourcesRead(ReadResourceRequestParams $params): ReadResourceResult
    {
        $params = $this->projectService->processResourceRequestParams($params);

        [$type, $path] = \explode('://', $params->uri, 2);
        $method = 'resource/' . $type . '/' . $path;

        $this->logger->debug('Handling resource read', [
            'resource' => $params->uri,
            'type' => $type,
            'path' => $path,
            'method' => $method,
        ]);

        try {
            // Create PSR request with the resource path
            $request = $this->requestAdapter->createPsrRequest($method);

            $response = $this->router->dispatch($request);
            \assert($response instanceof JsonResponse);

            return $response->getPayload();
        } catch (\Throwable $e) {
            $this->logError('Resource read', $method, $e);
            return new ReadResourceResult([]);
        }
    }
}
