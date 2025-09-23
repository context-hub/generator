<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\MCP\Tools;

use Butschster\ContextGenerator\Drafling\Domain\ValueObject\TemplateKey;
use Butschster\ContextGenerator\Drafling\Exception\DraflingException;
use Butschster\ContextGenerator\Drafling\Exception\TemplateNotFoundException;
use Butschster\ContextGenerator\Drafling\MCP\DTO\ProjectCreateRequest;
use Butschster\ContextGenerator\Drafling\Service\ProjectServiceInterface;
use Butschster\ContextGenerator\Drafling\Service\TemplateServiceInterface;
use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'drafling_create_project',
    description: 'Create a new project from an existing template with validation and proper initialization',
    title: 'Create Project',
)]
#[InputSchema(class: ProjectCreateRequest::class)]
final readonly class CreateProjectToolAction
{
    public function __construct(
        private LoggerInterface $logger,
        private ProjectServiceInterface $projectService,
        private TemplateServiceInterface $templateService,
    ) {}

    #[Post(path: '/tools/call/drafling_create_project', name: 'tools.drafling_create_project')]
    public function __invoke(ProjectCreateRequest $request): CallToolResult
    {
        $this->logger->info('Creating new project', [
            'template_id' => $request->templateId,
            'title' => $request->title,
        ]);

        try {
            // Validate request
            $validationErrors = $request->validate();
            if (!empty($validationErrors)) {
                return ToolResult::validationError($validationErrors);
            }

            // Verify template exists
            $templateKey = TemplateKey::fromString($request->templateId);
            if (!$this->templateService->templateExists($templateKey)) {
                return ToolResult::error("Template '{$request->templateId}' not found");
            }

            // Create project using domain service
            $project = $this->projectService->createProject($request);

            $this->logger->info('Project created successfully', [
                'project_id' => $project->id,
                'template' => $project->template,
            ]);

            // Format successful response according to MCP specification
            $response = [
                'success' => true,
                'project_id' => $project->id,
                'title' => $project->name,
                'template_id' => $project->template,
                'status' => $project->status,
                'created_at' => (new \DateTime())->format('c'),
            ];

            return ToolResult::success($response);

        } catch (TemplateNotFoundException $e) {
            $this->logger->error('Template not found', [
                'template_id' => $request->templateId,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error($e->getMessage());

        } catch (DraflingException $e) {
            $this->logger->error('Drafling error during project creation', [
                'template_id' => $request->templateId,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error($e->getMessage());

        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error creating project', [
                'template_id' => $request->templateId,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error('Failed to create project: ' . $e->getMessage());
        }
    }
}
