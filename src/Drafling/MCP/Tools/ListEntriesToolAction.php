<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\MCP\Tools;

use Butschster\ContextGenerator\Drafling\Domain\Model\Entry;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\ProjectId;
use Butschster\ContextGenerator\Drafling\Exception\DraflingException;
use Butschster\ContextGenerator\Drafling\Exception\ProjectNotFoundException;
use Butschster\ContextGenerator\Drafling\MCP\DTO\ListEntriesRequest;
use Butschster\ContextGenerator\Drafling\Service\EntryServiceInterface;
use Butschster\ContextGenerator\Drafling\Service\ProjectServiceInterface;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'drafling_list_entries',
    description: 'Retrieve a list of entries from a project with filtering, sorting, and pagination support',
    title: 'List Entries',
)]
#[InputSchema(class: ListEntriesRequest::class)]
final readonly class ListEntriesToolAction
{
    public function __construct(
        private LoggerInterface $logger,
        private EntryServiceInterface $entryService,
        private ProjectServiceInterface $projectService,
    ) {}

    #[Post(path: '/tools/call/drafling_list_entries', name: 'tools.drafling_list_entries')]
    public function __invoke(ListEntriesRequest $request): CallToolResult
    {
        $this->logger->info('Listing entries', [
            'project_id' => $request->projectId,
            'has_filters' => $request->hasFilters(),
            'filters' => $request->getFilters(),
            'limit' => $request->limit,
            'offset' => $request->offset,
        ]);

        try {
            // Validate request
            $validationErrors = $request->validate();
            if (!empty($validationErrors)) {
                return new CallToolResult([
                    new TextContent(
                        text: \json_encode([
                            'success' => false,
                            'error' => 'Validation failed',
                            'details' => $validationErrors,
                        ], JSON_PRETTY_PRINT),
                    ),
                ], isError: true);
            }

            // Verify project exists
            $projectId = ProjectId::fromString($request->projectId);
            if (!$this->projectService->projectExists($projectId)) {
                return new CallToolResult([
                    new TextContent(
                        text: \json_encode([
                            'success' => false,
                            'error' => "Project '{$request->projectId}' not found",
                        ], JSON_PRETTY_PRINT),
                    ),
                ], isError: true);
            }

            // Get entries with filters
            $allEntries = $this->entryService->getEntries($projectId, $request->getFilters());

            // Apply pagination
            $paginatedEntries = \array_slice(
                $allEntries,
                $request->offset,
                $request->limit,
            );

            // Format entries for response (using JsonSerializable)
            $entryData = \array_map(static function (Entry $entry) {
                $data = $entry->jsonSerialize();
                unset($data['content']);

                return $data;

            }, $paginatedEntries);

            $response = [
                'success' => true,
                'entries' => $entryData,
                'count' => \count($paginatedEntries),
                'total_count' => \count($allEntries),
                'pagination' => [
                    'limit' => $request->limit,
                    'offset' => $request->offset,
                    'has_more' => ($request->offset + \count($paginatedEntries)) < \count($allEntries),
                ],
                'filters_applied' => $request->hasFilters() ? $request->getFilters() : null,
            ];

            $this->logger->info('Entries listed successfully', [
                'project_id' => $request->projectId,
                'returned_count' => \count($paginatedEntries),
                'total_available' => \count($allEntries),
                'filters_applied' => $request->hasFilters(),
            ]);

            return new CallToolResult([
                new TextContent(
                    text: \json_encode($response, JSON_PRETTY_PRINT),
                ),
            ]);

        } catch (ProjectNotFoundException $e) {
            $this->logger->error('Project not found', [
                'project_id' => $request->projectId,
                'error' => $e->getMessage(),
            ]);

            return new CallToolResult([
                new TextContent(
                    text: \json_encode([
                        'success' => false,
                        'error' => $e->getMessage(),
                    ], JSON_PRETTY_PRINT),
                ),
            ], isError: true);

        } catch (DraflingException $e) {
            $this->logger->error('Drafling error listing entries', [
                'project_id' => $request->projectId,
                'error' => $e->getMessage(),
            ]);

            return new CallToolResult([
                new TextContent(
                    text: \json_encode([
                        'success' => false,
                        'error' => $e->getMessage(),
                    ], JSON_PRETTY_PRINT),
                ),
            ], isError: true);

        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error listing entries', [
                'project_id' => $request->projectId,
                'error' => $e->getMessage(),
            ]);

            return new CallToolResult([
                new TextContent(
                    text: \json_encode([
                        'success' => false,
                        'error' => 'Failed to list entries: ' . $e->getMessage(),
                    ], JSON_PRETTY_PRINT),
                ),
            ], isError: true);
        }
    }
}
