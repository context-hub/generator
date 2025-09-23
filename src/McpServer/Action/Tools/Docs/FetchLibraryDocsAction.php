<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Docs;

use Butschster\ContextGenerator\Lib\Context7Client\Context7ClientInterface;
use Butschster\ContextGenerator\Lib\Context7Client\Exception\Context7ClientException;
use Butschster\ContextGenerator\McpServer\Action\Tools\Docs\Dto\FetchLibraryDocsRequest;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'find-docs',
    description: 'Find documentation for a specific library',
    title: 'Context7 Find Documentation',
)]
#[InputSchema(class: FetchLibraryDocsRequest::class)]
final readonly class FetchLibraryDocsAction
{
    public function __construct(
        private LoggerInterface $logger,
        private Context7ClientInterface $context7Client,
    ) {}

    #[Post(path: '/tools/call/find-docs', name: 'tools.find-docs')]
    public function __invoke(FetchLibraryDocsRequest $request): CallToolResult
    {
        $this->logger->info('Processing find-docs tool');

        // Get params from the parsed body for POST requests
        $libraryId = \trim($request->id);
        $tokens = $request->tokens;
        $topic = $request->topic !== null ? \trim($request->topic) : null;

        if (empty($libraryId)) {
            return ToolResult::error('Missing id parameter');
        }

        try {
            $documentation = $this->context7Client->fetchLibraryDocumentation(
                libraryId: $libraryId,
                tokens: $tokens,
                topic: $topic,
            );

            return ToolResult::text($documentation);
        } catch (Context7ClientException $e) {
            return ToolResult::error($e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error in fetch-library-docs tool', [
                'libraryId' => $libraryId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ToolResult::error('Error fetching library documentation. Please try again later. ' . $e->getMessage());
        }
    }
}
