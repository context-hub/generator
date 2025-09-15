<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer;

use Butschster\ContextGenerator\McpServer\Routing\Handler\Prompts\PromptsHandlerInterface;
use Butschster\ContextGenerator\McpServer\Routing\Handler\Resources\ResourcesHandlerInterface;
use Butschster\ContextGenerator\McpServer\Routing\Handler\Tools\ToolsHandlerInterface;
use Mcp\Server\Server as McpServer;
use Mcp\Server\ServerRunner;
use Psr\Log\LoggerInterface;

/**
 * Refactored Server class that delegates to specific handlers
 */
final readonly class Server
{
    public function __construct(
        private LoggerInterface $logger,
        private PromptsHandlerInterface $promptsHandler,
        private ResourcesHandlerInterface $resourcesHandler,
        private ToolsHandlerInterface $toolsHandler,
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
            $this->promptsHandler->handlePromptsList(...),
        );

        $server->registerHandler(
            'prompts/get',
            $this->promptsHandler->handlePromptsGet(...),
        );

        // Register resources handlers
        $server->registerHandler(
            'resources/list',
            $this->resourcesHandler->handleResourcesList(...),
        );

        $server->registerHandler(
            'resources/read',
            $this->resourcesHandler->handleResourcesRead(...),
        );

        // Register tools handlers
        $server->registerHandler(
            'tools/list',
            $this->toolsHandler->handleToolsList(...),
        );

        $server->registerHandler(
            'tools/call',
            $this->toolsHandler->handleToolsCall(...),
        );
    }
}
