<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Routing;

use Butschster\ContextGenerator\Application\AppScope;
use Butschster\ContextGenerator\Lib\SchemaMapper\SchemaMapperInterface;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Psr\Http\Message\ServerRequestInterface;
use Spiral\Core\Attribute\Proxy;
use Spiral\Core\InvokerInterface;
use Spiral\Core\Scope;
use Spiral\Core\ScopeInterface;

final readonly class ActionCaller
{
    public function __construct(
        #[Proxy] private ScopeInterface $container,
        private SchemaMapperInterface $schemaMapper,
        private string $class,
    ) {}

    public function __invoke(ServerRequestInterface $request): mixed
    {
        $bindings = [
            ServerRequestInterface::class => $request,
        ];

        $reflection = new \ReflectionClass($this->class);
        $inputSchemaClass = $reflection->getAttributes(InputSchema::class)[0] ?? null;
        if ($inputSchemaClass !== null) {
            $inputSchema = $inputSchemaClass->newInstance();

            $input = $this->schemaMapper->toObject($request->getParsedBody(), $inputSchema->class);
            $bindings[$inputSchema->class] = $input;
        }

        return $this->container->runScope(
            bindings: new Scope(
                name: AppScope::McpServerRequest,
                bindings: $bindings,
            ),
            scope: fn(InvokerInterface $invoker): object => $invoker->invoke([$this->class, '__invoke']),
        );
    }
}
