<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\MCP\Tools;

use Butschster\ContextGenerator\Research\Domain\ValueObject\TemplateKey;
use Butschster\ContextGenerator\Research\Exception\ResearchException;
use Butschster\ContextGenerator\Research\Exception\TemplateNotFoundException;
use Butschster\ContextGenerator\Research\MCP\DTO\ResearchCreateRequest;
use Butschster\ContextGenerator\Research\Service\ResearchServiceInterface;
use Butschster\ContextGenerator\Research\Service\TemplateServiceInterface;
use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'research-create',
    description: 'Create a new research from an existing template with validation and proper initialization',
    title: 'Create Research',
)]
#[InputSchema(class: ResearchCreateRequest::class)]
final readonly class CreateResearchToolAction
{
    public function __construct(
        private LoggerInterface $logger,
        private ResearchServiceInterface $service,
        private TemplateServiceInterface $templateService,
    ) {}

    #[Post(path: '/tools/call/research-create', name: 'tools.research-create')]
    public function __invoke(ResearchCreateRequest $request): CallToolResult
    {
        $this->logger->info('Creating new research', [
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

            // Create research using domain service
            $research = $this->service->create($request);

            $this->logger->info('Research created successfully', [
                'research_id' => $research->id,
                'template' => $research->template,
            ]);

            // Format successful response according to MCP specification
            $response = [
                'success' => true,
                'research_id' => $research->id,
                'title' => $research->name,
                'template_id' => $research->template,
                'status' => $research->status,
                'created_at' => (new \DateTime())->format('c'),
            ];

            return ToolResult::success($response);

        } catch (TemplateNotFoundException $e) {
            $this->logger->error('Template not found', [
                'template_id' => $request->templateId,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error($e->getMessage());

        } catch (ResearchException $e) {
            $this->logger->error('Error during research creation', [
                'template_id' => $request->templateId,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error($e->getMessage());

        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error creating research', [
                'template_id' => $request->templateId,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error('Failed to create research: ' . $e->getMessage());
        }
    }
}
