<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer;

use Butschster\ContextGenerator\McpServer\Registry\McpItemsRegistry;
use Butschster\ContextGenerator\McpServer\Routing\RouteRegistrar;
use Psr\Log\LoggerInterface;
use Spiral\Core\Attribute\Singleton;

#[Singleton]
final class ServerFactory implements ServerFactoryInterface
{
    /**
     * @var array<class-string>
     */
    private array $actions = [];

    public function __construct(
        private readonly RouteRegistrar $registrar,
        private readonly McpItemsRegistry $registry,
        private readonly LoggerInterface $logger,
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

    public function create(): Server
    {
        // Register all classes with MCP item attributes. Should be before registering controllers!
        $this->registry->registerMany($this->actions);

        // Register all controllers for routing
        $this->registrar->registerControllers($this->actions);

        // Create the server
        return new Server(
            router: $this->registrar->router,
            logger: $this->logger,
        );
    }
}
