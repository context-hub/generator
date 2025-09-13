<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Routing;

use Laminas\Diactoros\Response\JsonResponse;
use League\Route\Route;
use League\Route\Strategy\ApplicationStrategy;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Spiral\Exceptions\ExceptionReporterInterface;

final class McpResponseStrategy extends ApplicationStrategy
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ExceptionReporterInterface $reporter,
    ) {}

    #[\Override]
    public function invokeRouteCallable(Route $route, ServerRequestInterface $request): ResponseInterface
    {
        try {
            $this->logger->info('Invoking route callable', [
                'route' => $route->getName(),
                'method' => $request->getMethod(),
                'uri' => (string) $request->getUri(),
            ]);

            $controller = $route->getCallable($this->getContainer());
            $response = $controller($request, $route->getVars());

            if ($response instanceof ResponseInterface) {
                return $response;
            }

            return new JsonResponse($response);
        } catch (\Throwable $e) {
            $this->logger->error('Error while handling request', [
                'exception' => $e,
                'request' => $request,
            ]);

            $this->reporter->report($e);

            return new JsonResponse([
                'error' => 'Internal Server Error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
