<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\MCP\Tools;

use Butschster\ContextGenerator\Drafling\Domain\ValueObject\EntryId;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\ProjectId;
use Butschster\ContextGenerator\Drafling\Exception\DraflingException;
use Butschster\ContextGenerator\Drafling\Exception\EntryNotFoundException;
use Butschster\ContextGenerator\Drafling\Exception\ProjectNotFoundException;
use Butschster\ContextGenerator\Drafling\MCP\DTO\ReadEntryRequest;
use Butschster\ContextGenerator\Drafling\Service\EntryServiceInterface;
use Butschster\ContextGenerator\Drafling\Service\ProjectServiceInterface;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'drafling_read_entry',
    description: 'Retrieve detailed information about a specific entry including content and metadata',
    title: 'Read Entry',
)]
#[InputSchema(class: ReadEntryRequest::class)]
final readonly class ReadEntryToolAction
{
    public function __construct(
        private LoggerInterface $logger,
        private EntryServiceInterface $entryService,
        private ProjectServiceInterface $projectService,
    ) {}

    #[Post(path: '/tools/call/drafling_read_entry', name: 'tools.drafling_read_entry')]
    public function __invoke(ReadEntryRequest $request): CallToolResult
    {
        $this->logger->info('Reading entry', [
            'project_id' => $request->projectId,
            'entry_id' => $request->entryId,
        ]);

        try {
            // Validate request
            $validationErrors = $request->validate();
            if (!empty($validationErrors)) {
                return ToolResult::validationError($validationErrors);
            }

            // Verify project exists
            $projectId = ProjectId::fromString($request->projectId);
            if (!$this->projectService->projectExists($projectId)) {
                return ToolResult::error("Project '{$request->projectId}' not found");
            }

            // Get the entry
            $entryId = EntryId::fromString($request->entryId);
            $entry = $this->entryService->getEntry($projectId, $entryId);

            if ($entry === null) {
                return ToolResult::error("Entry '{$request->entryId}' not found in project '{$request->projectId}'");
            }

            $this->logger->info('Entry read successfully', [
                'project_id' => $request->projectId,
                'entry_id' => $request->entryId,
                'title' => $entry->title,
            ]);

            return ToolResult::success($entry);

        } catch (ProjectNotFoundException $e) {
            $this->logger->error('Project not found', [
                'project_id' => $request->projectId,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error($e->getMessage());

        } catch (EntryNotFoundException $e) {
            $this->logger->error('Entry not found', [
                'project_id' => $request->projectId,
                'entry_id' => $request->entryId,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error($e->getMessage());

        } catch (DraflingException $e) {
            $this->logger->error('Drafling error reading entry', [
                'project_id' => $request->projectId,
                'entry_id' => $request->entryId,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error($e->getMessage());

        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error reading entry', [
                'project_id' => $request->projectId,
                'entry_id' => $request->entryId,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error('Failed to read entry: ' . $e->getMessage());
        }
    }
}
