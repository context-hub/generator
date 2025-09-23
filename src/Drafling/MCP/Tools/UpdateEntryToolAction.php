<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\MCP\Tools;

use Butschster\ContextGenerator\Drafling\Domain\ValueObject\EntryId;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\ProjectId;
use Butschster\ContextGenerator\Drafling\Exception\DraflingException;
use Butschster\ContextGenerator\Drafling\Exception\EntryNotFoundException;
use Butschster\ContextGenerator\Drafling\Exception\ProjectNotFoundException;
use Butschster\ContextGenerator\Drafling\MCP\DTO\EntryUpdateRequest;
use Butschster\ContextGenerator\Drafling\Service\EntryServiceInterface;
use Butschster\ContextGenerator\Drafling\Service\ProjectServiceInterface;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'drafling_update_entry',
    description: 'Update existing content entries with new title, content, status, or tags while preserving entry metadata',
    title: 'Update Entry',
)]
#[InputSchema(class: EntryUpdateRequest::class)]
final readonly class UpdateEntryToolAction
{
    public function __construct(
        private LoggerInterface $logger,
        private EntryServiceInterface $entryService,
        private ProjectServiceInterface $projectService,
    ) {}

    #[Post(path: '/tools/call/drafling_update_entry', name: 'tools.drafling_update_entry')]
    public function __invoke(EntryUpdateRequest $request): CallToolResult
    {
        $this->logger->info('Updating entry', [
            'project_id' => $request->projectId,
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

            // Verify entry exists
            $entryId = EntryId::fromString($request->entryId);
            if (!$this->entryService->entryExists($projectId, $entryId)) {
                return new CallToolResult([
                    new TextContent(
                        text: \json_encode([
                            'success' => false,
                            'error' => "Entry '{$request->entryId}' not found in project '{$request->projectId}'",
                        ], JSON_PRETTY_PRINT),
                    ),
                ], isError: true);
            }

            // Update entry using domain service
            $updatedEntry = $this->entryService->updateEntry($projectId, $entryId, $request);

            $this->logger->info('Entry updated successfully', [
                'project_id' => $request->projectId,
                'entry_id' => $request->entryId,
                'title' => $updatedEntry->title,
            ]);

            // Format successful response according to MCP specification
            $response = [
                'success' => true,
                'entry_id' => $updatedEntry->entryId,
                'title' => $updatedEntry->title,
                'entry_type' => $updatedEntry->entryType,
                'category' => $updatedEntry->category,
                'status' => $updatedEntry->status,
                'content_type' => 'markdown', // Default content type for Drafling
                'updated_at' => $updatedEntry->updatedAt->format('c'),
                'tags' => $updatedEntry->tags,
                'changes_applied' => $this->getAppliedChanges($request),
            ];

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

        } catch (EntryNotFoundException $e) {
            $this->logger->error('Entry not found', [
                'project_id' => $request->projectId,
                'entry_id' => $request->entryId,
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
            $this->logger->error('Drafling error during entry update', [
                'project_id' => $request->projectId,
                'entry_id' => $request->entryId,
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
            $this->logger->error('Unexpected error updating entry', [
                'project_id' => $request->projectId,
                'entry_id' => $request->entryId,
                'error' => $e->getMessage(),
            ]);

            return new CallToolResult([
                new TextContent(
                    text: \json_encode([
                        'success' => false,
                        'error' => 'Failed to update entry: ' . $e->getMessage(),
                    ], JSON_PRETTY_PRINT),
                ),
            ], isError: true);
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
