<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Routing\Handler;

use Butschster\ContextGenerator\McpServer\ProjectService\ProjectServiceInterface;
use Butschster\ContextGenerator\McpServer\Routing\Mcp2PsrRequestAdapter;
use Laminas\Diactoros\Response\JsonResponse;
use League\Route\Router;
use Psr\Log\LoggerInterface;

/**
 * Abstract base class providing common functionality for all MCP handlers
 */
abstract readonly class BaseHandler
{
    public function __construct(
        protected Router $router,
        protected LoggerInterface $logger,
        protected ProjectServiceInterface $projectService,
        protected Mcp2PsrRequestAdapter $requestAdapter,
    ) {}

    /**
     * Handle a route using the router system
     */
    protected function handleRoute(string $method, array $params = []): mixed
    {
        $this->logger->debug("Handling route: $method", $params);

        // Create PSR request from MCP method and params
        $request = $this->requestAdapter->createPsrRequest($method, $params);

        try {
            $response = $this->router->dispatch($request);
            \assert($response instanceof JsonResponse);

            // Convert the response back to appropriate MCP type
            return $this->projectService->processResponse($response->getPayload());
        } catch (\Throwable $e) {
            $this->logger->error('Route handling error', [
                'method' => $method,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Log error and return error result
     */
    protected function logError(string $context, string $method, \Throwable $e): void
    {
        $this->logger->error("$context error", [
            'method' => $method,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
