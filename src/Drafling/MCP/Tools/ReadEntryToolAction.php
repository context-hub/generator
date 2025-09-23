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
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
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
            'include_content' => $request->includeContent,
            'include_metadata' => $request->includeMetadata,
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

            // Get the entry
            $entryId = EntryId::fromString($request->entryId);
            $entry = $this->entryService->getEntry($projectId, $entryId);

            if ($entry === null) {
                return new CallToolResult([
                    new TextContent(
                        text: \json_encode([
                            'success' => false,
                            'error' => "Entry '{$request->entryId}' not found in project '{$request->projectId}'",
                        ], JSON_PRETTY_PRINT),
                    ),
                ], isError: true);
            }

            // Build response based on inclusion flags
            $response = [
                'success' => true,
                'entry_id' => $entry->entryId,
                'title' => $entry->title,
                'entry_type' => $entry->entryType,
                'category' => $entry->category,
                'status' => $entry->status,
                'content_type' => 'markdown',
                'created_at' => $entry->createdAt->format('c'),
                'updated_at' => $entry->updatedAt->format('c'),
            ];

            // Include content if requested
            if ($request->includeContent) {
                $response['content'] = $entry->content ?? '';
            }

            // Include metadata if requested
            if ($request->includeMetadata) {
                $response['metadata'] = [
                    'tags' => $entry->tags,
                    'file_path' => $entry->filePath ?? null,
                ];
            }

            $this->logger->info('Entry read successfully', [
                'project_id' => $request->projectId,
                'entry_id' => $request->entryId,
                'title' => $entry->title,
                'included_content' => $request->includeContent,
                'included_metadata' => $request->includeMetadata,
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
            $this->logger->error('Drafling error reading entry', [
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
            $this->logger->error('Unexpected error reading entry', [
                'project_id' => $request->projectId,
                'entry_id' => $request->entryId,
                'error' => $e->getMessage(),
            ]);

            return new CallToolResult([
                new TextContent(
                    text: \json_encode([
                        'success' => false,
                        'error' => 'Failed to read entry: ' . $e->getMessage(),
                    ], JSON_PRETTY_PRINT),
                ),
            ], isError: true);
        }
    }
}
