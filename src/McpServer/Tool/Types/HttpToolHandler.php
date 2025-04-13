<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool\Types;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Lib\HttpClient\Exception\HttpException;
use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;
use Butschster\ContextGenerator\Lib\HttpClient\HttpResponse;
use Butschster\ContextGenerator\Lib\Variable\VariableReplacementProcessor;
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Butschster\ContextGenerator\McpServer\Tool\Config\HttpToolRequest;
use Butschster\ContextGenerator\McpServer\Tool\Config\ToolDefinition;
use Butschster\ContextGenerator\McpServer\Tool\Exception\ToolExecutionException;
use Butschster\ContextGenerator\McpServer\Tool\Provider\ToolArgumentsProvider;
use Psr\Log\LoggerInterface;

#[LoggerPrefix(prefix: 'tool.http')]
final readonly class HttpToolHandler extends AbstractToolHandler
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private VariableResolver $variables,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($logger);
    }

    public function supports(string $type): bool
    {
        return $type === 'http';
    }

    protected function doExecute(ToolDefinition $tool, array $arguments = []): array
    {
        if (!isset($tool->extra['requests']) || !\is_array($tool->extra['requests'])) {
            throw new ToolExecutionException('HTTP tool must have a "requests" property with at least one request');
        }

        return $this->executeRequests($tool, $tool->extra['requests'], $arguments);
    }

    /**
     * Execute HTTP requests with optional arguments.
     *
     * @param ToolDefinition $tool The tool being executed
     * @param array<array<string, mixed>> $requestConfigs Request configurations to execute
     * @param array<string, mixed> $arguments Arguments for variable replacement
     * @return array<string, mixed> Execution result
     */
    private function executeRequests(ToolDefinition $tool, array $requestConfigs, array $arguments = []): array
    {
        $results = [];

        foreach ($requestConfigs as $index => $requestConfig) {
            $this->logger?->info('Processing HTTP request', [
                'index' => $index,
                'config' => $requestConfig,
            ]);

            try {
                // Parse request configuration
                $httpRequest = $this->processRequestWithArguments($tool, $requestConfig, $arguments);

                $this->logger?->info('Executing HTTP request', [
                    'method' => $httpRequest->method,
                    'url' => $httpRequest->getFullUrl(),
                    'headers' => \array_keys($httpRequest->headers),
                ]);

                // Execute the request based on the HTTP method
                $response = $this->executeHttpRequest($httpRequest);

                // Format the response for output
                $responseData = $this->formatResponse($response);

                $results[] = [
                    'response' => $responseData,
                    'success' => $response->isSuccess(),
                ];
            } catch (\Throwable $e) {
                $this->logger?->error('HTTP request execution failed', [
                    'index' => $index,
                    'error' => $e->getMessage(),
                ]);

                $results[] = [
                    'error' => $e->getMessage(),
                    'success' => false,
                ];
                break;
            }
        }

        return [
            'output' => \json_encode($results),
        ];
    }

    /**
     * Process a request configuration by replacing variable placeholders.
     *
     * @param ToolDefinition $tool The tool definition with schema information
     * @param array<string, mixed> $requestConfig The request configuration
     * @param array<string, mixed> $arguments The arguments to use for replacement
     * @return HttpToolRequest The processed HTTP request
     */
    private function processRequestWithArguments(
        ToolDefinition $tool,
        array $requestConfig,
        array $arguments,
    ): HttpToolRequest {
        // Create arguments provider
        $argsProvider = new ToolArgumentsProvider($arguments, $tool->schema);

        // Create a processor for variable replacement
        $variables = $this->variables->with(new VariableReplacementProcessor($argsProvider));

        // Process URL
        if (isset($requestConfig['url'])) {
            $requestConfig['url'] = $variables->resolve($requestConfig['url']);
        }

        // Process headers
        if (isset($requestConfig['headers']) && \is_array($requestConfig['headers'])) {
            foreach ($requestConfig['headers'] as $key => $value) {
                $requestConfig['headers'][$key] = $variables->resolve($value);
            }
        }

        $data = [];

        foreach ($arguments as $key => $value) {
            $data[$key] = $value;
        }

        // Process query parameters
        if (isset($requestConfig['query']) && \is_array($requestConfig['query'])) {
            foreach ($requestConfig['query'] as $key => $value) {
                $data[$key] = $variables->resolve($value);
            }
        }

        // Process query parameters
        if (isset($requestConfig['body']) && \is_array($requestConfig['body'])) {
            foreach ($requestConfig['body'] as $key => $value) {
                $data[$key] = $variables->resolve($value);
            }
        }

        return HttpToolRequest::fromArray($requestConfig, $data);
    }

    /**
     * Execute an HTTP request based on its method
     */
    private function executeHttpRequest(HttpToolRequest $request): HttpResponse
    {
        $headers = $request->headers;

        // Set default content type for requests with body
        if ($request->body !== null && $request->method !== 'GET' && !isset($headers['Content-Type'])) {
            if (\is_array($request->body)) {
                $headers['Content-Type'] = 'application/json';
            } else {
                $headers['Content-Type'] = 'text/plain';
            }
        }

        return match ($request->method) {
            'GET' => $this->httpClient->get($request->getFullUrl(), $headers),
            'POST' => $this->httpClient->post(
                $request->getFullUrl(),
                $headers,
                $request->getBodyAsString(),
            ),
            default => throw new ToolExecutionException("HTTP method {$request->method} not supported yet"),
        };
    }

    /**
     * Format an HTTP response for the output
     */
    private function formatResponse(HttpResponse $response): mixed
    {
        try {
            return $response->getJson();
        } catch (HttpException) {
        }
        // If not JSON, return raw body
        return $response->getBody();
    }
}
