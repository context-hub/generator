<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Middleware;

use Butschster\ContextGenerator\Application\AppScope;
use Mcp\Server\Authentication\Contract\UserProviderInterface;
use Mcp\Server\Authentication\Provider\InMemoryUserProvider;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Spiral\Core\Attribute\Proxy;
use Spiral\Core\Scope;
use Spiral\Core\ScopeInterface;

final readonly class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        #[Proxy]
        private ScopeInterface $scope,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $auth = $request->getAttribute('auth');

        return $this->scope->runScope(
            bindings: new Scope(
                name: AppScope::McpOauth,
                bindings: [
                    UserProviderInterface::class => new InMemoryUserProvider(authInfo: $auth),
                ],
            ),
            scope: static fn() => $handler->handle($request),
        );
    }
}
