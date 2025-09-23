<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\MCP\Tools;

use Butschster\ContextGenerator\Drafling\Domain\ValueObject\ProjectId;
use Butschster\ContextGenerator\Drafling\Exception\DraflingException;
use Butschster\ContextGenerator\Drafling\Exception\ProjectNotFoundException;
use Butschster\ContextGenerator\Drafling\MCP\DTO\AddProjectMemoryRequest;
use Butschster\ContextGenerator\Drafling\Service\ProjectServiceInterface;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'drafling_add_project_memory',
    description: 'Add a new memory entry to project for LLM context storage and retrieval',
    title: 'Add Project Memory',
)]
#[InputSchema(class: AddProjectMemoryRequest::class)]
final readonly class AddProjectMemoryToolAction
{
    public function __construct(
        private LoggerInterface $logger,
        private ProjectServiceInterface $projectService,
    ) {}

    #[Post(path: '/tools/call/drafling_add_project_memory', name: 'tools.drafling_add_project_memory')]
    public function __invoke(AddProjectMemoryRequest $request): CallToolResult
    {
        $this->logger->info('Adding memory to project', [
            'project_id' => $request->projectId,
            'memory_length' => \strlen($request->memory),
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

            // Add memory to project using domain service
            $updatedProject = $this->projectService->addProjectMemory($projectId, $request->memory);

            $this->logger->info('Memory added to project successfully', [
                'project_id' => $request->projectId,
                'memory_count' => \count($updatedProject->memory),
                'title' => $updatedProject->name,
            ]);

            // Format successful response according to MCP specification
            $response = [
                'success' => true,
                'project_id' => $updatedProject->id,
                'title' => $updatedProject->name,
                'status' => $updatedProject->status,
                'project_type' => $updatedProject->template,
                'updated_at' => (new \DateTime())->format('c'),
                'memory_count' => \count($updatedProject->memory),
                'memory_added' => $request->memory,
                'metadata' => [
                    'description' => $updatedProject->description,
                    'tags' => $updatedProject->tags,
                    'entry_dirs' => $updatedProject->entryDirs,
                    'memory' => $updatedProject->memory,
                ],
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
            $this->logger->error('Drafling error during memory addition', [
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
            $this->logger->error('Unexpected error adding memory to project', [
                'project_id' => $request->projectId,
                'error' => $e->getMessage(),
            ]);

            return new CallToolResult([
                new TextContent(
                    text: \json_encode([
                        'success' => false,
                        'error' => 'Failed to add memory to project: ' . $e->getMessage(),
                    ], JSON_PRETTY_PRINT),
                ),
            ], isError: true);
        }
    }
}
