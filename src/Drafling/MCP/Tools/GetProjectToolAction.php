<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\MCP\Tools;

use Butschster\ContextGenerator\Drafling\Domain\ValueObject\ProjectId;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\TemplateKey;
use Butschster\ContextGenerator\Drafling\Exception\DraflingException;
use Butschster\ContextGenerator\Drafling\Exception\ProjectNotFoundException;
use Butschster\ContextGenerator\Drafling\MCP\DTO\GetProjectRequest;
use Butschster\ContextGenerator\Drafling\Service\ProjectServiceInterface;
use Butschster\ContextGenerator\Drafling\Service\TemplateServiceInterface;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'drafling_get_project',
    description: 'Retrieve a single project by ID with basic information including title, status, template, and metadata',
    title: 'Get Project',
)]
#[InputSchema(class: GetProjectRequest::class)]
final readonly class GetProjectToolAction
{
    public function __construct(
        private LoggerInterface $logger,
        private ProjectServiceInterface $projectService,
        private TemplateServiceInterface $templateService,
    ) {}

    #[Post(path: '/tools/call/drafling_get_project', name: 'tools.drafling_get_project')]
    public function __invoke(GetProjectRequest $request): CallToolResult
    {
        $this->logger->info('Getting project', [
            'project_id' => $request->id,
        ]);

        try {
            // Validate request
            $validationErrors = $request->validate();
            if (!empty($validationErrors)) {
                return ToolResult::validationError($validationErrors);
            }

            // Get project
            $projectId = ProjectId::fromString($request->id);
            $project = $this->projectService->getProject($projectId);

            if ($project === null) {
                return ToolResult::error("Project '{$request->id}' not found");
            }

            $this->logger->info('Project retrieved successfully', [
                'project_id' => $project->id,
                'template' => $project->template,
            ]);

            $template = $this->templateService->getTemplate(TemplateKey::fromString($project->template));

            // Format project for response
            $response = [
                'success' => true,
                'project' => [
                    'project_id' => $project->id,
                    'title' => $project->name,
                    'status' => $project->status,
                    'metadata' => [
                        'description' => $project->description,
                        'tags' => $project->tags,
                        'memory' => $project->memory,
                    ],
                ],
                'template' => $template,
            ];

            return ToolResult::success($response);

        } catch (ProjectNotFoundException $e) {
            $this->logger->error('Project not found', [
                'project_id' => $request->id,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error($e->getMessage());

        } catch (DraflingException $e) {
            $this->logger->error('Drafling error getting project', [
                'project_id' => $request->id,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error($e->getMessage());

        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error getting project', [
                'project_id' => $request->id,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error('Failed to get project: ' . $e->getMessage());
        }
    }
}
