<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\MCP\Tools;

use Butschster\ContextGenerator\Research\Domain\ValueObject\ResearchId;
use Butschster\ContextGenerator\Research\Domain\ValueObject\TemplateKey;
use Butschster\ContextGenerator\Research\Exception\ResearchException;
use Butschster\ContextGenerator\Research\Exception\ResearchNotFoundException;
use Butschster\ContextGenerator\Research\MCP\DTO\GetResearchRequest;
use Butschster\ContextGenerator\Research\Service\ResearchServiceInterface;
use Butschster\ContextGenerator\Research\Service\TemplateServiceInterface;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'research-get',
    description: 'Retrieve a single research by ID',
    title: 'Get Research',
)]
#[InputSchema(class: GetResearchRequest::class)]
final readonly class GetResearchToolAction
{
    public function __construct(
        private LoggerInterface $logger,
        private ResearchServiceInterface $service,
        private TemplateServiceInterface $templateService,
    ) {}

    #[Post(path: '/tools/call/research-get', name: 'tools.research-get')]
    public function __invoke(GetResearchRequest $request): CallToolResult
    {
        $this->logger->info('Getting research', [
            'research_id' => $request->id,
        ]);

        try {
            // Validate request
            $validationErrors = $request->validate();
            if (!empty($validationErrors)) {
                return ToolResult::validationError($validationErrors);
            }

            // Get research
            $researchId = ResearchId::fromString($request->id);
            $research = $this->service->get($researchId);

            if ($research === null) {
                return ToolResult::error("Research '{$request->id}' not found");
            }

            $this->logger->info('Research retrieved successfully', [
                'research_id' => $research->id,
                'template' => $research->template,
            ]);

            $template = $this->templateService->getTemplate(TemplateKey::fromString($research->template));

            // Format research for response
            return ToolResult::success([
                'success' => true,
                'research' => [
                    'id' => $research->id,
                    'title' => $research->name,
                    'status' => $research->status,
                    'metadata' => [
                        'description' => $research->description,
                        'tags' => $research->tags,
                        'memory' => $research->memory,
                    ],
                ],
                'template' => $template,
            ]);

        } catch (ResearchNotFoundException $e) {
            $this->logger->error('Research not found', [
                'research_id' => $request->id,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error($e->getMessage());

        } catch (ResearchException $e) {
            $this->logger->error('Error getting research', [
                'research_id' => $request->id,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error($e->getMessage());

        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error getting research', [
                'research_id' => $request->id,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error('Failed to get research: ' . $e->getMessage());
        }
    }
}
