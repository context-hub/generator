<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Routing;

use Butschster\ContextGenerator\McpServer\Routing\Attribute\Route;
use League\Route\Router;
use Spiral\Core\Attribute\Proxy;
use Spiral\Core\BinderInterface;
use Spiral\Core\FactoryInterface;

final readonly class RouteRegistrar
{
    private BinderInterface $binder;

    public function __construct(
        public Router $router,
        #[Proxy] private FactoryInterface $factory,
        BinderInterface $binder,
    ) {
        $this->binder = $binder->getBinder('mcp.server');
    }

    /**
     * Register routes from a controller class
     */
    public function registerController(string $controllerClass): void
    {
        $this->binder->bindSingleton($controllerClass, $controllerClass);

        $reflectionClass = new \ReflectionClass($controllerClass);

        // Get the controller prefix if defined
        // Find all methods with Route attribute
        foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            // Find all Route attributes
            $routeAttributes = $method->getAttributes(Route::class, \ReflectionAttribute::IS_INSTANCEOF);
            if (empty($routeAttributes)) {
                continue;
            }

            $this->registerMethodRoutes($routeAttributes[0]->newInstance(), $controllerClass);
        }
    }

    /**
     * Register multiple controllers at once
     */
    public function registerControllers(array $controllerClasses): void
    {
        foreach ($controllerClasses as $controllerClass) {
            $this->registerController($controllerClass);
        }
    }

    /**
     * Register routes for a single controller method
     */
    private function registerMethodRoutes(Route $route, string $controllerClass): void
    {
        // Combine prefix with route path
        $path = $this->normalizePath($route->path);

        $registeredRoute = $this->router->map(
            method: $route->method,
            path: $path,
            handler: $this->factory->make(ActionCaller::class, [
                'class' => $controllerClass,
            ]),
        );

        // Set route name if provided
        if ($route->name !== null) {
            $registeredRoute->setName($route->name);
        }
    }

    /**
     * Normalize a path to ensure proper formatting
     */
    private function normalizePath(string $path): string
    {
        // Replace multiple slashes with a single slash
        $path = \preg_replace('#/+#', '/', $path);

        // Ensure path starts with a slash
        if (!\str_starts_with((string) $path, '/')) {
            $path = '/' . $path;
        }

        return $path;
    }
}
