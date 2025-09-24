<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\MCP\Tools;

use Butschster\ContextGenerator\Research\Exception\ResearchException;
use Butschster\ContextGenerator\Research\MCP\DTO\ListResearchesRequest;
use Butschster\ContextGenerator\Research\Service\ResearchServiceInterface;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'researches-list',
    description: 'Retrieve a list of user\'s researches with filtering, and pagination support',
    title: 'List researches',
)]
#[InputSchema(class: ListResearchesRequest::class)]
final readonly class ListResearchesToolAction
{
    public function __construct(
        private LoggerInterface $logger,
        private ResearchServiceInterface $service,
    ) {}

    #[Post(path: '/tools/call/researches-list', name: 'tools.researches-list')]
    public function __invoke(ListResearchesRequest $request): CallToolResult
    {
        $this->logger->info('Listing researches', [
            'has_filters' => $request->hasFilters(),
            'filters' => $request->getFilters(),
            'limit' => $request->limit,
            'offset' => $request->offset,
        ]);

        try {
            // Validate request
            $validationErrors = $request->validate();
            if (!empty($validationErrors)) {
                return ToolResult::validationError($validationErrors);
            }

            // Get researches with filters
            $researches = $this->service->findAll($request->getFilters());

            // Apply pagination
            $paginatedResearches = \array_slice(
                $researches,
                $request->offset,
                $request->limit,
            );

            $response = [
                'success' => true,
                'researches' => $paginatedResearches,
                'count' => \count($paginatedResearches),
                'total_count' => \count($researches),
                'pagination' => [
                    'limit' => $request->limit,
                    'offset' => $request->offset,
                    'has_more' => ($request->offset + \count($paginatedResearches)) < \count($researches),
                ],
            ];

            $this->logger->info('Researches listed successfully', [
                'returned_count' => \count($paginatedResearches),
                'total_available' => \count($researches),
                'filters_applied' => $request->hasFilters(),
            ]);

            return ToolResult::success($response);

        } catch (ResearchException $e) {
            $this->logger->error('Error listing researches', [
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error($e->getMessage());

        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error listing researches', [
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error('Failed to list researches: ' . $e->getMessage());
        }
    }
}
