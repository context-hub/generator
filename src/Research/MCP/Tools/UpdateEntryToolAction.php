<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\MCP\Tools;

use Butschster\ContextGenerator\Research\Domain\ValueObject\EntryId;
use Butschster\ContextGenerator\Research\Domain\ValueObject\ResearchId;
use Butschster\ContextGenerator\Research\Exception\ResearchException;
use Butschster\ContextGenerator\Research\Exception\EntryNotFoundException;
use Butschster\ContextGenerator\Research\Exception\ResearchNotFoundException;
use Butschster\ContextGenerator\Research\MCP\DTO\EntryUpdateRequest;
use Butschster\ContextGenerator\Research\Service\EntryServiceInterface;
use Butschster\ContextGenerator\Research\Service\ResearchServiceInterface;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'research-entry-update',
    description: 'Update existing research entries with new title, content, status, or tags while preserving entry metadata',
    title: 'Update Entry',
)]
#[InputSchema(class: EntryUpdateRequest::class)]
final readonly class UpdateEntryToolAction
{
    public function __construct(
        private LoggerInterface $logger,
        private EntryServiceInterface $entryService,
        private ResearchServiceInterface $service,
    ) {}

    #[Post(path: '/tools/call/research-entry-update', name: 'tools.research-entry-update')]
    public function __invoke(EntryUpdateRequest $request): CallToolResult
    {
        $this->logger->info('Updating entry', [
            'research_id' => $request->researchId,
            'entry_id' => $request->entryId,
            'has_title' => $request->title !== null,
            'has_description' => $request->description !== null,
            'has_content' => $request->content !== null,
            'has_status' => $request->status !== null,
            'has_tags' => $request->tags !== null,
            'has_text_replace' => $request->textReplace !== null,
        ]);

        try {
            // Validate request
            $validationErrors = $request->validate();
            if (!empty($validationErrors)) {
                return ToolResult::validationError($validationErrors);
            }

            $researchId = ResearchId::fromString($request->researchId);
            if (!$this->service->exists($researchId)) {
                return ToolResult::error("Research '{$request->researchId}' not found");
            }

            // Verify entry exists
            $entryId = EntryId::fromString($request->entryId);
            if (!$this->entryService->entryExists($researchId, $entryId)) {
                return ToolResult::error("Entry '{$request->entryId}' not found in research '{$request->researchId}'");
            }

            // Update entry using domain service
            $updatedEntry = $this->entryService->updateEntry($researchId, $entryId, $request);

            $this->logger->info('Entry updated successfully', [
                'research_id' => $request->researchId,
                'entry_id' => $request->entryId,
                'title' => $updatedEntry->title,
            ]);

            return ToolResult::success([
                'success' => true,
            ]);
        } catch (ResearchNotFoundException $e) {
            $this->logger->error('Research not found', [
                'research_id' => $request->researchId,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error($e->getMessage());

        } catch (EntryNotFoundException $e) {
            $this->logger->error('Entry not found', [
                'research_id' => $request->researchId,
                'entry_id' => $request->entryId,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error($e->getMessage());

        } catch (ResearchException $e) {
            $this->logger->error('Error during research entry update', [
                'research_id' => $request->researchId,
                'entry_id' => $request->entryId,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error($e->getMessage());

        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error updating entry', [
                'research_id' => $request->researchId,
                'entry_id' => $request->entryId,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error('Failed to update entry: ' . $e->getMessage());
        }
    }

    /**
     * Get list of changes applied based on the request
     */
    private function getAppliedChanges(EntryUpdateRequest $request): array
    {
        $changes = [];

        if ($request->title !== null) {
            $changes[] = 'title';
        }

        if ($request->description !== null) {
            $changes[] = 'description';
        }

        if ($request->content !== null) {
            $changes[] = 'content';
        }

        if ($request->status !== null) {
            $changes[] = 'status';
        }

        if ($request->tags !== null) {
            $changes[] = 'tags';
        }

        if ($request->textReplace !== null) {
            $changes[] = 'text_replacement';
        }

        return $changes;
    }
}
