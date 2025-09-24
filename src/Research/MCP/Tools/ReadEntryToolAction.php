<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\MCP\Tools;

use Butschster\ContextGenerator\Research\Domain\ValueObject\EntryId;
use Butschster\ContextGenerator\Research\Domain\ValueObject\ResearchId;
use Butschster\ContextGenerator\Research\Exception\ResearchException;
use Butschster\ContextGenerator\Research\Exception\EntryNotFoundException;
use Butschster\ContextGenerator\Research\Exception\ResearchNotFoundException;
use Butschster\ContextGenerator\Research\MCP\DTO\ReadEntryRequest;
use Butschster\ContextGenerator\Research\Service\EntryServiceInterface;
use Butschster\ContextGenerator\Research\Service\ResearchServiceInterface;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'research-entry-read',
    description: 'Retrieve detailed information about a specific entry',
    title: 'Read Entry',
)]
#[InputSchema(class: ReadEntryRequest::class)]
final readonly class ReadEntryToolAction
{
    public function __construct(
        private LoggerInterface $logger,
        private EntryServiceInterface $entryService,
        private ResearchServiceInterface $service,
    ) {}

    #[Post(path: '/tools/call/research-entry-read', name: 'tools.research-entry-read')]
    public function __invoke(ReadEntryRequest $request): CallToolResult
    {
        $this->logger->info('Reading entry', [
            'research_id' => $request->researchId,
            'entry_id' => $request->entryId,
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

            // Get the entry
            $entryId = EntryId::fromString($request->entryId);
            $entry = $this->entryService->getEntry($researchId, $entryId);

            if ($entry === null) {
                return ToolResult::error("Entry '{$request->entryId}' not found in research '{$request->researchId}'");
            }

            $this->logger->info('Entry read successfully', [
                'research_id' => $request->researchId,
                'entry_id' => $request->entryId,
                'title' => $entry->title,
            ]);

            return ToolResult::success($entry);

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
            $this->logger->error('Error reading research entry', [
                'research_id' => $request->researchId,
                'entry_id' => $request->entryId,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error($e->getMessage());

        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error reading entry', [
                'research_id' => $request->researchId,
                'entry_id' => $request->entryId,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error('Failed to read entry: ' . $e->getMessage());
        }
    }
}
