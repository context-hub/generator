<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Server\Driver;

use Butschster\ContextGenerator\McpServer\Config\HttpTransportConfig;
use Mcp\Server\HttpServerRunner;
use Mcp\Server\InitializationOptions;
use Mcp\Server\Server as McpServer;
use Mcp\Server\Transport\Http\HttpMessage;
use Psr\Log\LoggerInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

final readonly class HttpServerDriver implements ServerDriverInterface
{
    public function __construct(
        private HttpTransportConfig $httpConfig,
        private LoggerInterface $logger,
    ) {}

    public function run(McpServer $server, InitializationOptions $initOptions): void
    {
        $runner = new HttpServerRunner(
            server: $server,
            initOptions: $initOptions,
            httpOptions: $this->httpConfig->toHttpOptions(),
            logger: $this->logger,
        );

        $this->startSwooleServer($runner);
    }

    private function startSwooleServer(HttpServerRunner $runner): void
    {
        $http = new Server($this->httpConfig->host, $this->httpConfig->port);

        $http->on('request', function (Request $request, Response $response) use ($runner): void {
            $requestMessage = $this->createHttpMessage($request);
            $result = $runner->handleRequest($requestMessage);

            $response->end($result->getBody());
        });

        $http->start();
    }

    private function createHttpMessage(Request $request): HttpMessage
    {
        $requestMessage = new HttpMessage();

        // Set headers
        foreach ($request->header as $key => $value) {
            $requestMessage->setHeader($key, $value);
        }

        // Set query parameters
        if ($request->get) {
            $requestMessage->setQueryParams($request->get);
        }

        // Set body
        if ($request->post) {
            $requestMessage->setBody(\json_encode($request->post));
        }

        // Set method
        $requestMessage->setMethod($request->server['request_method']);

        return $requestMessage;
    }
}
