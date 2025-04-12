<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer;

use Butschster\ContextGenerator\Application\AppScope;
use Butschster\ContextGenerator\McpServer\ProjectService\ProjectServiceInterface;
use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\McpServer\Registry\McpItemsRegistry;
use Butschster\ContextGenerator\McpServer\Routing\RouteRegistrar;
use Spiral\Core\Attribute\Proxy;
use Spiral\Core\Attribute\Singleton;
use Spiral\Core\Scope;
use Spiral\Core\ScopeInterface;

#[Singleton]
final class ServerRunner implements ServerRunnerInterface
{
    /**
     * @var array<class-string>
     */
    private array $actions = [];

    public function __construct(
        #[Proxy] private readonly ScopeInterface $scope,
        private readonly ProjectServiceInterface $projectService,
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

    public function run(string $name): void
    {
        $this->scope->runScope(
            bindings: new Scope(
                name: AppScope::McpServer,
            ),
            scope: function (
                RouteRegistrar $registrar,
                McpItemsRegistry $registry,
                HasPrefixLoggerInterface $logger,
            ) use ($name): void {
                // Register all classes with MCP item attributes. Should be before registering controllers!
                $registry->registerMany($this->actions);

                // Register all controllers for routing
                $registrar->registerControllers($this->actions);

                // Create the server
                (new Server(
                    router: $registrar->router,
                    logger: $logger,
                    projectService: $this->projectService,
                ))->run($name);
            },
        );
    }
}
