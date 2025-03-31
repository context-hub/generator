<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Routing;

use Butschster\ContextGenerator\Application\AppScope;
use Psr\Http\Message\ServerRequestInterface;
use Spiral\Core\Attribute\Proxy;
use Spiral\Core\InvokerInterface;
use Spiral\Core\Scope;
use Spiral\Core\ScopeInterface;

final readonly class ActionCaller
{
    public function __construct(
        #[Proxy] private ScopeInterface $container,
        private string $class,
    ) {}

    public function __invoke(ServerRequestInterface $request): mixed
    {
        return $this->container->runScope(
            bindings: new Scope(
                name: AppScope::McpServerRequest,
                bindings: [
                    ServerRequestInterface::class => $request,
                ],
            ),
            scope: fn(InvokerInterface $invoker) => $invoker->invoke([$this->class, '__invoke']),
        );
    }
}
