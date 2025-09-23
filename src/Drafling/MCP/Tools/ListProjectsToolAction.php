<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\MCP\Tools;

use Butschster\ContextGenerator\Drafling\Exception\DraflingException;
use Butschster\ContextGenerator\Drafling\MCP\DTO\ListProjectsRequest;
use Butschster\ContextGenerator\Drafling\Service\ProjectServiceInterface;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'drafling_list_projects',
    description: 'Retrieve a list of user\'s projects with filtering, sorting, and pagination support',
    title: 'List Projects',
)]
#[InputSchema(class: ListProjectsRequest::class)]
final readonly class ListProjectsToolAction
{
    public function __construct(
        private LoggerInterface $logger,
        private ProjectServiceInterface $projectService,
    ) {}

    #[Post(path: '/tools/call/drafling_list_projects', name: 'tools.drafling_list_projects')]
    public function __invoke(ListProjectsRequest $request): CallToolResult
    {
        $this->logger->info('Listing projects', [
            'has_filters' => $request->hasFilters(),
            'filters' => $request->getFilters(),
            'limit' => $request->limit,
            'offset' => $request->offset,
            'sort_by' => $request->sortBy,
        ]);

        try {
            // Validate request
            $validationErrors = $request->validate();
            if (!empty($validationErrors)) {
                return ToolResult::validationError($validationErrors);
            }

            // Get projects with filters
            $allProjects = $this->projectService->listProjects($request->getFilters());

            // Apply sorting
            $sortedProjects = $this->applySorting($allProjects, $request->getSortingOptions());

            // Apply pagination
            $paginatedProjects = \array_slice(
                $sortedProjects,
                $request->offset,
                $request->limit,
            );

            // Format projects for response (using JsonSerializable)
            $projectData = $paginatedProjects;

            $response = [
                'success' => true,
                'projects' => $projectData,
                'count' => \count($paginatedProjects),
                'total_count' => \count($allProjects),
                'pagination' => [
                    'limit' => $request->limit,
                    'offset' => $request->offset,
                    'has_more' => ($request->offset + \count($paginatedProjects)) < \count($allProjects),
                ],
                'filters_applied' => $request->hasFilters() ? $request->getFilters() : null,
            ];

            $this->logger->info('Projects listed successfully', [
                'returned_count' => \count($paginatedProjects),
                'total_available' => \count($allProjects),
                'filters_applied' => $request->hasFilters(),
            ]);

            return ToolResult::success($response);

        } catch (DraflingException $e) {
            $this->logger->error('Drafling error listing projects', [
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error($e->getMessage());

        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error listing projects', [
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error('Failed to list projects: ' . $e->getMessage());
        }
    }

    /**
     * Apply sorting to projects array
     */
    private function applySorting(array $projects, array $sortingOptions): array
    {
        $sortBy = $sortingOptions['sort_by'];
        $sortDirection = \strtolower((string) $sortingOptions['sort_direction']);

        \usort($projects, function ($a, $b) use ($sortBy, $sortDirection) {
            $valueA = $this->getProjectFieldValue($a, $sortBy);
            $valueB = $this->getProjectFieldValue($b, $sortBy);

            // Handle null values
            if ($valueA === $valueB) {
                return 0;
            }

            if ($valueA === null) {
                return 1;
            }

            if ($valueB === null) {
                return -1;
            }

            // Compare values
            $result = $valueA <=> $valueB;

            return $sortDirection === 'desc' ? -$result : $result;
        });

        return $projects;
    }

    /**
     * Get field value from project for sorting
     * @param mixed $project
     */
    private function getProjectFieldValue($project, string $field): mixed
    {
        return match ($field) {
            'name' => $project->name,
            'status' => $project->status,
            'template' => $project->template,
            'created_at' => null, // Would need actual timestamps from domain
            'updated_at' => null, // Would need actual timestamps from domain
            default => $project->name,
        };
    }
}
