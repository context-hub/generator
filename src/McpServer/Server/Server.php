<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Server;

use Butschster\ContextGenerator\McpServer\Routing\Handler\Prompts\PromptsHandlerInterface;
use Butschster\ContextGenerator\McpServer\Routing\Handler\Resources\ResourcesHandlerInterface;
use Butschster\ContextGenerator\McpServer\Routing\Handler\Tools\ToolsHandlerInterface;
use Mcp\Server\Server as McpServer;
use Psr\Log\LoggerInterface;

/**
 * Server class that delegates transport concerns to drivers
 */
final readonly class Server
{
    public function __construct(
        private LoggerInterface $logger,
        private PromptsHandlerInterface $promptsHandler,
        private ResourcesHandlerInterface $resourcesHandler,
        private ToolsHandlerInterface $toolsHandler,
        private ServerDriverFactory $driverFactory,
    ) {}

    /**
     * Start the server using the appropriate driver
     */
    public function run(string $name): void
    {
        $server = new McpServer(name: $name, logger: $this->logger);
        $this->configureServer($server);

        $this->driverFactory->create()->run(
            server: $server,
            initOptions: $server->createInitializationOptions(),
        );
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
