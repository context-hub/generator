<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Docs;

use Butschster\ContextGenerator\Lib\Context7Client\Context7ClientInterface;
use Butschster\ContextGenerator\Lib\Context7Client\Exception\Context7ClientException;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'library-search',
    description: 'Search for available documentation libraries in Context7',
    title: 'Context7 Library Search',
)]
#[InputSchema(
    name: 'query',
    type: 'string',
    description: 'Provide a library name to search for relevant libraries id (Like "Spiral Framework", "Symfony", "Laravel", etc.)',
    required: true,
)]
#[InputSchema(
    name: 'maxResults',
    type: 'number',
    description: 'Maximum number of results to return (default is 5)',
    required: false,
)]
final readonly class LibrarySearchAction
{
    public function __construct(
        private LoggerInterface $logger,
        private Context7ClientInterface $context7Client,
    ) {}

    #[Post(path: '/tools/call/library-search', name: 'tools.library-search')]
    public function __invoke(ServerRequestInterface $request): CallToolResult
    {
        $this->logger->info('Processing library-search tool');

        // Get params from the parsed body for POST requests
        $parsedBody = $request->getParsedBody();
        $query = \trim($parsedBody['query'] ?? '');
        $maxResults = \min(10, \max(1, (int) ($parsedBody['maxResults'] ?? 5)));

        if (empty($query)) {
            return new CallToolResult([
                new TextContent(
                    text: 'Error: Missing query parameter',
                ),
            ], isError: true);
        }

        try {
            $searchResult = $this->context7Client->searchLibraries(
                query: $query,
                maxResults: $maxResults,
            );

            return new CallToolResult([
                new TextContent(
                    text: \json_encode($searchResult),
                ),
            ]);
        } catch (Context7ClientException $e) {
            return new CallToolResult([
                new TextContent(text: $e->getMessage()),
            ], isError: true);
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error in library-search tool', [
                'query' => $query,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new CallToolResult([
                new TextContent(
                    text: 'Error searching libraries: ' . $e->getMessage(),
                ),
            ], isError: true);
        }
    }
}
