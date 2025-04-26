<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Docs;

use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'docs-search',
    description: 'Search for documentation libraries available. Use "context-request" tool to get the context documents.',
)]
#[InputSchema(
    name: 'query',
    type: 'string',
    description: 'The search query to find documentation libraries',
    required: true,
)]
final readonly class DocsSearchAction
{
    private const string CONTEXT7_SEARCH_URL = 'https://context7.com/api/v1/search';

    public function __construct(
        private LoggerInterface $logger,
        private HttpClientInterface $httpClient,
    ) {}

    #[Post(path: '/tools/call/docs-search', name: 'tools.docs-search')]
    public function __invoke(ServerRequestInterface $request): CallToolResult
    {
        $this->logger->info('Processing docs-search tool');

        // Get params from the parsed body for POST requests
        $parsedBody = $request->getParsedBody();
        $query = \trim($parsedBody['query'] ?? '');

        if (empty($query)) {
            return new CallToolResult([
                new TextContent(
                    text: 'Error: Missing query parameter',
                ),
            ], isError: true);
        }

        try {
            $url = self::CONTEXT7_SEARCH_URL . '?' . \http_build_query(['query' => $query]);

            $this->logger->debug('Sending request to Context7 search API', [
                'url' => $url,
            ]);

            $headers = [
                'User-Agent' => 'CTX Bot',
                'Accept' => 'application/json',
            ];

            $response = $this->httpClient->get($url, $headers);

            if (!$response->isSuccess()) {
                $statusCode = $response->getStatusCode();
                $this->logger->error('Context7 search request failed', [
                    'statusCode' => $statusCode,
                ]);
                return new CallToolResult([
                    new TextContent(
                        text: "Context7 search request failed with status code {$statusCode}",
                    ),
                ], isError: true);
            }

            $data = $response->getJson(true);

            if (!isset($data['results']) || !\is_array($data['results'])) {
                $this->logger->warning('Unexpected response format from Context7', [
                    'response' => $data,
                ]);
                return new CallToolResult([
                    new TextContent(
                        text: 'Unexpected response format from Context7 search API',
                    ),
                ], isError: true);
            }

            // Limit the number of results if needed
            $results = $data['results'];

            $this->logger->info('Documentation libraries found', [
                'count' => \count($results),
                'query' => $query,
            ]);

            // Format the results for display
            $formattedResults = \array_map(static fn(array $library) => [
                'id' => $library['id'] ?? '',
                'title' => $library['title'] ?? '',
                'description' => $library['description'] ?? '',
                'branch' => $library['branch'] ?? 'main',
                'lastUpdateDate' => $library['lastUpdateDate'] ?? '',
                'totalTokens' => $library['totalTokens'] ?? 0,
                'totalPages' => $library['totalPages'] ?? 0,
                'usage' => \sprintf(
                    "Use in your context config: { type: 'docs', library: '%s', topic: 'your-topic' }",
                    \ltrim($library['id'] ?? '', '/'),
                ),
            ], $results);

            return new CallToolResult([
                new TextContent(
                    text: \json_encode([
                        'count' => \count($formattedResults),
                        'results' => $formattedResults,
                    ], JSON_PRETTY_PRINT),
                ),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Error searching documentation libraries', [
                'query' => $query,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new CallToolResult([
                new TextContent(
                    text: 'Error: ' . $e->getMessage(),
                ),
            ], isError: true);
        }
    }
}
