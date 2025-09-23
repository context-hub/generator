<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Docs;

use Butschster\ContextGenerator\Lib\Context7Client\Context7ClientInterface;
use Butschster\ContextGenerator\Lib\Context7Client\Exception\Context7ClientException;
use Butschster\ContextGenerator\McpServer\Action\Tools\Docs\Dto\LibrarySearchRequest;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'library-search',
    description: 'Search for available documentation libraries in Context7',
    title: 'Context7 Library Search',
)]
#[InputSchema(class: LibrarySearchRequest::class)]
final readonly class LibrarySearchAction
{
    public function __construct(
        private LoggerInterface $logger,
        private Context7ClientInterface $context7Client,
    ) {}

    #[Post(path: '/tools/call/library-search', name: 'tools.library-search')]
    public function __invoke(LibrarySearchRequest $request): CallToolResult
    {
        $this->logger->info('Processing library-search tool');

        // Get params from the parsed body for POST requests
        $query = \trim($request->query);
        $maxResults = \min(10, \max(1, $request->maxResults ?? 5));

        if (empty($query)) {
            return ToolResult::error('Missing query parameter');
        }

        try {
            $searchResult = $this->context7Client->searchLibraries(
                query: $query,
                maxResults: $maxResults,
            );

            return ToolResult::success($searchResult);
        } catch (Context7ClientException $e) {
            return ToolResult::error($e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error in library-search tool', [
                'query' => $query,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ToolResult::error('Error searching libraries: ' . $e->getMessage());
        }
    }
}
