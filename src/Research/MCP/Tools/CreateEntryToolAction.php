<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\MCP\Tools;

use Butschster\ContextGenerator\Research\Domain\ValueObject\ResearchId;
use Butschster\ContextGenerator\Research\Exception\ResearchException;
use Butschster\ContextGenerator\Research\Exception\ResearchNotFoundException;
use Butschster\ContextGenerator\Research\MCP\DTO\EntryCreateRequest;
use Butschster\ContextGenerator\Research\Service\EntryServiceInterface;
use Butschster\ContextGenerator\Research\Service\ResearchServiceInterface;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'research-create-entry',
    description: 'Add new content entries to research categories',
    title: 'Create Entry',
)]
#[InputSchema(class: EntryCreateRequest::class)]
final readonly class CreateEntryToolAction
{
    public function __construct(
        private LoggerInterface $logger,
        private EntryServiceInterface $entryService,
        private ResearchServiceInterface $service,
    ) {}

    #[Post(path: '/tools/call/research-create-entry', name: 'tools.research-create-entry')]
    public function __invoke(EntryCreateRequest $request): CallToolResult
    {
        $this->logger->info('Creating new entry', [
            'research_id' => $request->researchId,
            'category' => $request->category,
            'entry_type' => $request->entryType,
            'has_description' => $request->description !== null,
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

            // Create entry using domain service
            $entry = $this->entryService->createEntry($researchId, $request);

            $this->logger->info('Entry created successfully', [
                'research_id' => $request->researchId,
                'entry_id' => $entry->entryId,
                'title' => $entry->title,
            ]);

            // Format successful response according to MCP specification
            $response = [
                'success' => true,
                'entry_id' => $entry->entryId,
                'title' => $entry->title,
                'entry_type' => $entry->entryType,
                'category' => $entry->category,
                'status' => $entry->status,
                'content_type' => 'markdown',
                'created_at' => $entry->createdAt->format('c'),
            ];

            return ToolResult::success($response);

        } catch (ResearchNotFoundException $e) {
            $this->logger->error('Research not found', [
                'research_id' => $request->researchId,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error($e->getMessage());

        } catch (ResearchException $e) {
            $this->logger->error('Research error during entry creation', [
                'research_id' => $request->researchId,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error($e->getMessage());

        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error creating entry', [
                'research_id' => $request->researchId,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error('Failed to create entry: ' . $e->getMessage());
        }
    }
}
