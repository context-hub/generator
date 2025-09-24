<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\MCP\Tools;

use Butschster\ContextGenerator\Research\Domain\Model\Entry;
use Butschster\ContextGenerator\Research\Domain\ValueObject\ResearchId;
use Butschster\ContextGenerator\Research\Exception\ResearchException;
use Butschster\ContextGenerator\Research\Exception\ResearchNotFoundException;
use Butschster\ContextGenerator\Research\MCP\DTO\ListEntriesRequest;
use Butschster\ContextGenerator\Research\Service\EntryServiceInterface;
use Butschster\ContextGenerator\Research\Service\ResearchServiceInterface;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'research-entries',
    description: 'Retrieve a list of entries from a research',
    title: 'List Entries',
)]
#[InputSchema(class: ListEntriesRequest::class)]
final readonly class ListEntriesToolAction
{
    public function __construct(
        private LoggerInterface $logger,
        private EntryServiceInterface $entryService,
        private ResearchServiceInterface $service,
    ) {}

    #[Post(path: '/tools/call/research-entries', name: 'tools.research-entries')]
    public function __invoke(ListEntriesRequest $request): CallToolResult
    {
        $this->logger->info('Listing entries', [
            'research_id' => $request->researchId,
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

            // Verify research exists
            $researchId = ResearchId::fromString($request->researchId);
            if (!$this->service->exists($researchId)) {
                return ToolResult::error("Research '{$request->researchId}' not found");
            }

            // Get entries with filters
            $allEntries = $this->entryService->findAll($researchId, $request->getFilters());

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
                'research_id' => $request->researchId,
                'returned_count' => \count($paginatedEntries),
                'total_available' => \count($allEntries),
                'filters_applied' => $request->hasFilters(),
            ]);

            return ToolResult::success($response);

        } catch (ResearchNotFoundException $e) {
            $this->logger->error('Research not found', [
                'research_id' => $request->researchId,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error($e->getMessage());

        } catch (ResearchException $e) {
            $this->logger->error('Error listing research entries', [
                'research_id' => $request->researchId,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error($e->getMessage());

        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error listing entries', [
                'research_id' => $request->researchId,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error('Failed to list entries: ' . $e->getMessage());
        }
    }
}
