<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\MCP\Tools;

use Butschster\ContextGenerator\Drafling\Domain\ValueObject\ProjectId;
use Butschster\ContextGenerator\Drafling\Exception\DraflingException;
use Butschster\ContextGenerator\Drafling\Exception\ProjectNotFoundException;
use Butschster\ContextGenerator\Drafling\MCP\DTO\ProjectUpdateRequest;
use Butschster\ContextGenerator\Drafling\Service\ProjectServiceInterface;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'drafling_update_project',
    description: 'Update existing project properties including title, description, status, tags, entry directories, and memory entries',
    title: 'Update Project',
)]
#[InputSchema(class: ProjectUpdateRequest::class)]
final readonly class UpdateProjectToolAction
{
    public function __construct(
        private LoggerInterface $logger,
        private ProjectServiceInterface $projectService,
    ) {}

    #[Post(path: '/tools/call/drafling_update_project', name: 'tools.drafling_update_project')]
    public function __invoke(ProjectUpdateRequest $request): CallToolResult
    {
        $this->logger->info('Updating project', [
            'project_id' => $request->projectId,
            'has_title' => $request->title !== null,
            'has_description' => $request->description !== null,
            'has_status' => $request->status !== null,
            'has_tags' => $request->tags !== null,
            'has_entry_dirs' => $request->entryDirs !== null,
            'has_memory' => $request->memory !== null,
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

            // Update project using domain service
            $updatedProject = $this->projectService->updateProject($projectId, $request);

            $this->logger->info('Project updated successfully', [
                'project_id' => $request->projectId,
                'title' => $updatedProject->name,
                'status' => $updatedProject->status,
            ]);

            // Format successful response according to MCP specification
            $response = [
                'success' => true,
                'project_id' => $updatedProject->id,
                'title' => $updatedProject->name,
                'status' => $updatedProject->status,
                'project_type' => $updatedProject->template,
                'updated_at' => (new \DateTime())->format('c'), // Would need actual update timestamp from domain
                'metadata' => [
                    'description' => $updatedProject->description,
                    'tags' => $updatedProject->tags,
                    'entry_dirs' => $updatedProject->entryDirs,
                    'memory' => $updatedProject->memory,
                ],
                'changes_applied' => $this->getAppliedChanges($request),
            ];

            return ToolResult::success($response);

        } catch (ProjectNotFoundException $e) {
            $this->logger->error('Project not found', [
                'project_id' => $request->projectId,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error($e->getMessage());

        } catch (DraflingException $e) {
            $this->logger->error('Drafling error during project update', [
                'project_id' => $request->projectId,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error($e->getMessage());

        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error updating project', [
                'project_id' => $request->projectId,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error('Failed to update project: ' . $e->getMessage());
        }
    }

    /**
     * Get list of changes applied based on the request
     */
    private function getAppliedChanges(ProjectUpdateRequest $request): array
    {
        $changes = [];

        if ($request->title !== null) {
            $changes[] = 'title';
        }

        if ($request->description !== null) {
            $changes[] = 'description';
        }

        if ($request->status !== null) {
            $changes[] = 'status';
        }

        if ($request->tags !== null) {
            $changes[] = 'tags';
        }

        if ($request->entryDirs !== null) {
            $changes[] = 'entry_directories';
        }

        if ($request->memory !== null) {
            $changes[] = 'memory';
        }

        return $changes;
    }
}
