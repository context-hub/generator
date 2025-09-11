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
    name: 'find-docs',
    description: 'Find documentation for a specific library',
    title: 'Context7 Find Documentation',
)]
#[InputSchema(
    name: 'id',
    type: 'string',
    description: 'The library ID to fetch documentation for. Use "library-search" tool to find library IDs by name.',
    required: true,
)]
#[InputSchema(
    name: 'tokens',
    type: 'number',
    description: 'Maximum number of tokens to return (optional)',
    required: false,
)]
#[InputSchema(
    name: 'topic',
    type: 'string',
    description: 'Specific topic to focus on (optional)',
    required: false,
)]
final readonly class FetchLibraryDocsAction
{
    public function __construct(
        private LoggerInterface $logger,
        private Context7ClientInterface $context7Client,
    ) {}

    #[Post(path: '/tools/call/find-docs', name: 'tools.find-docs')]
    public function __invoke(ServerRequestInterface $request): CallToolResult
    {
        $this->logger->info('Processing find-docs tool');

        // Get params from the parsed body for POST requests
        $parsedBody = $request->getParsedBody();
        $libraryId = \trim($parsedBody['id'] ?? '');
        $tokens = isset($parsedBody['tokens']) ? (int) $parsedBody['tokens'] : null;
        $topic = isset($parsedBody['topic']) ? \trim($parsedBody['topic']) : null;

        if (empty($libraryId)) {
            return new CallToolResult([
                new TextContent(
                    text: 'Error: Missing libraryId parameter',
                ),
            ], isError: true);
        }

        try {
            $documentation = $this->context7Client->fetchLibraryDocumentation(
                libraryId: $libraryId,
                tokens: $tokens,
                topic: $topic,
            );

            return new CallToolResult([
                new TextContent(text: $documentation),
            ]);
        } catch (Context7ClientException $e) {
            return new CallToolResult([
                new TextContent(text: $e->getMessage()),
            ], isError: true);
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error in fetch-library-docs tool', [
                'libraryId' => $libraryId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new CallToolResult([
                new TextContent(
                    text: 'Error fetching library documentation. Please try again later. ' . $e->getMessage(),
                ),
            ], isError: true);
        }
    }
}
