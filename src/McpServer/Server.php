<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer;

use Butschster\ContextGenerator\McpServer\ProjectService\ProjectServiceInterface;
use Butschster\ContextGenerator\McpServer\Routing\Mcp2PsrRequestAdapter;
use Laminas\Diactoros\Response\JsonResponse;
use League\Route\Router;
use Mcp\Server\Server as McpServer;
use Mcp\Server\ServerRunner;
use Mcp\Types\CallToolRequestParams;
use Mcp\Types\CallToolResult;
use Mcp\Types\GetPromptRequestParams;
use Mcp\Types\GetPromptResult;
use Mcp\Types\ListPromptsResult;
use Mcp\Types\ListResourcesResult;
use Mcp\Types\ListToolsResult;
use Mcp\Types\ReadResourceRequestParams;
use Mcp\Types\ReadResourceResult;
use Mcp\Types\TextContent;
use Psr\Log\LoggerInterface;

final readonly class Server
{
    public function __construct(
        private Router $router,
        private LoggerInterface $logger,
        private ProjectServiceInterface $projectService,
        private Mcp2PsrRequestAdapter $requestFactory = new Mcp2PsrRequestAdapter(),
    ) {}

    /**
     * Start the server
     */
    public function run(string $name): void
    {
        $server = new McpServer(name: $name, logger: $this->logger);
        $this->configureServer($server);

        $initOptions = $server->createInitializationOptions();
        $runner = new ServerRunner(server: $server, initOptions: $initOptions, logger: $this->logger);
        $runner->run();
    }

    /**
     * Configure all handlers for the server
     */
    private function configureServer(McpServer $server): void
    {
        // Register prompts handlers
        $server->registerHandler(
            'prompts/list',
            fn() => $this->handleRoute('prompts/list', ListPromptsResult::class),
        );

        $server->registerHandler(
            'prompts/get',
            fn($params) => $this->handlePromptGetRoute($params),
        );

        // Register resources handlers
        $server->registerHandler(
            'resources/list',
            fn() => $this->handleRoute('resources/list', ListResourcesResult::class),
        );

        $server->registerHandler(
            'resources/read',
            fn($params) => $this->handleResourceRead($params),
        );

        // Register tools handlers
        $server->registerHandler(
            'tools/list',
            fn() => $this->handleRoute('tools/list', ListToolsResult::class),
        );

        $server->registerHandler(
            'tools/call',
            fn($params) => $this->handleToolCall($params),
        );
    }

    /**
     * Handle a route using the router
     */
    private function handleRoute(string $method, string $class, array $params = []): mixed
    {
        $this->logger->debug("Handling route: $method", $params);

        // Create PSR request from MCP method and params
        $request = $this->requestFactory->createPsrRequest($method, $params);

        // Dispatch the request through the router
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
        }

        return new $class([]);
    }

    /**
     * Special handler for tool calls to map to specific routes
     */
    private function handleToolCall(CallToolRequestParams $params): CallToolResult
    {
        $params = $this->projectService->processToolRequestParams($params);

        $method = 'tools/call/' . $params->name;
        $arguments = $params->arguments ?? [];

        $this->logger->debug('Handling tool call', [
            'tool' => $params->name,
            'method' => $method,
            'arguments' => $arguments,
        ]);

        // Create PSR request with the tool name in the path and arguments as POST body
        $request = $this->requestFactory->createPsrRequest($method, $arguments);

        try {
            $response = $this->router->dispatch($request);
            \assert($response instanceof JsonResponse);
            return $response->getPayload();
        } catch (\Throwable $e) {
            $this->logger->error('Tool call error', [
                'tool' => $params->name,
                'error' => $e->getMessage(),
            ]);
            return new CallToolResult([new TextContent(text: $e->getMessage())], isError: true);
        }
    }

    private function handleResourceRead(ReadResourceRequestParams $params): ReadResourceResult
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

        // Create PSR request with the tool name in the path and arguments as POST body
        $request = $this->requestFactory->createPsrRequest($method);

        try {
            $response = $this->router->dispatch($request);
            \assert($response instanceof JsonResponse);
            // Convert the response back to appropriate MCP type
            return $response->getPayload();
        } catch (\Throwable $e) {
            $this->logger->error('Resource read error', [
                'resource' => $params->uri,
                'error' => $e->getMessage(),
            ]);
        }
        return new ReadResourceResult([]);
    }

    private function handlePromptGetRoute(GetPromptRequestParams $params): GetPromptResult
    {
        $params = $this->projectService->processPromptRequestParams($params);

        $name = $params->name;
        $arguments = $params->arguments;

        $method = 'prompt/' . $name;

        $this->logger->debug('Handling prompt get', [
            'prompt' => $name,
            'method' => $method,
            'arguments' => (array) $arguments->jsonSerialize(),
        ]);

        // Create PSR request with the tool name in the path and arguments as POST body
        $request = $this->requestFactory->createPsrRequest($method, (array) $arguments->jsonSerialize());

        try {
            $response = $this->router->dispatch($request);
            \assert($response instanceof JsonResponse);

            // Convert the response back to appropriate MCP type
            return $response->getPayload();
        } catch (\Throwable $e) {
            $this->logger->error('Prompt get error', [
                'prompt' => $params->name,
                'error' => $e->getMessage(),
            ]);
        }
        return new GetPromptResult([]);
    }
}
