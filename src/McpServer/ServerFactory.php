<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer;

use Butschster\ContextGenerator\McpServer\Registry\McpItemsRegistry;
use Butschster\ContextGenerator\McpServer\Routing\RouteRegistrar;
use Psr\Log\LoggerInterface;

final class ServerFactory
{
    /**
     * @var array<class-string>
     */
    private array $actions = [];

    public function __construct(
        private readonly RouteRegistrar $registrar,
        private readonly McpItemsRegistry $registry,
    ) {}

    /**
     * Register a new action class
     *
     * @param class-string $class
     */
    public function registerAction(string $class): void
    {
        $this->actions[] = $class;
    }

    /**
     * Create a new McpServer instance with attribute-based routing
     */
    public function create(LoggerInterface $logger): Server
    {
        // Register all classes with MCP item attributes. Should be before registering controllers!
        $this->registry->registerMany($this->actions);

        // Register all controllers for routing
        $this->registrar->registerControllers($this->actions);

        // Create the server
        return new Server(
            router: $this->registrar->router,
            logger: $logger,
        );
    }
}
