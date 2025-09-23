<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\MCP\Tools;

use Butschster\ContextGenerator\Drafling\Domain\ValueObject\ProjectId;
use Butschster\ContextGenerator\Drafling\Exception\DraflingException;
use Butschster\ContextGenerator\Drafling\Exception\ProjectNotFoundException;
use Butschster\ContextGenerator\Drafling\MCP\DTO\EntryCreateRequest;
use Butschster\ContextGenerator\Drafling\Service\EntryServiceInterface;
use Butschster\ContextGenerator\Drafling\Service\ProjectServiceInterface;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'drafling_create_entry',
    description: 'Add new content entries to project categories with template validation and automatic title generation',
    title: 'Create Entry',
)]
#[InputSchema(class: EntryCreateRequest::class)]
final readonly class CreateEntryToolAction
{
    public function __construct(
        private LoggerInterface $logger,
        private EntryServiceInterface $entryService,
        private ProjectServiceInterface $projectService,
    ) {}

    #[Post(path: '/tools/call/drafling_create_entry', name: 'tools.drafling_create_entry')]
    public function __invoke(EntryCreateRequest $request): CallToolResult
    {
        $this->logger->info('Creating new entry', [
            'project_id' => $request->projectId,
            'category' => $request->category,
            'entry_type' => $request->entryType,
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

            // Create entry using domain service
            $entry = $this->entryService->createEntry($projectId, $request);

            $this->logger->info('Entry created successfully', [
                'project_id' => $request->projectId,
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
                'content_type' => 'markdown', // Default content type for Drafling
                'created_at' => $entry->createdAt->format('c'),
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

        } catch (DraflingException $e) {
            $this->logger->error('Drafling error during entry creation', [
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
            $this->logger->error('Unexpected error creating entry', [
                'project_id' => $request->projectId,
                'error' => $e->getMessage(),
            ]);

            return new CallToolResult([
                new TextContent(
                    text: \json_encode([
                        'success' => false,
                        'error' => 'Failed to create entry: ' . $e->getMessage(),
                    ], JSON_PRETTY_PRINT),
                ),
            ], isError: true);
        }
    }
}
