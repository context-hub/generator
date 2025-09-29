<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Middleware;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final readonly class LoggerMiddleware implements MiddlewareInterface
{
    public function __construct(
        #[LoggerPrefix('MCP Server')]
        private LoggerInterface $logger,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->logger->debug('Request received', [
            'request' => $request,
        ]);

        $response = $handler->handle($request);

        $this->logger->debug('Response sent', [
            'response' => $response,
        ]);

        return $response;
    }
}
