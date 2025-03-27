<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer;

use Butschster\ContextGenerator\McpServer\Action\RouteProvider;
use League\Route\Strategy\StrategyInterface;
use Psr\Log\LoggerInterface;
use Spiral\Core\Container;

final readonly class ServerFactory
{
    public function __construct(
        private Container $container,
    ) {}

    /**
     * Create a new McpServer instance with routing
     */
    public function create(LoggerInterface $logger): Server
    {
        // Create route provider
        $routeProvider = new RouteProvider(
            $this->container,
            $this->container->get(StrategyInterface::class),
        );

        // Create the server
        return new Server(
            router: $routeProvider->registerRoutes(),
            logger: $logger,
        );
    }
}
