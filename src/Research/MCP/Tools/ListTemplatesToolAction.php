<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\MCP\Tools;

use Butschster\ContextGenerator\Research\Exception\ResearchException;
use Butschster\ContextGenerator\Research\MCP\DTO\ListTemplatesRequest;
use Butschster\ContextGenerator\Research\Service\TemplateServiceInterface;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'research-templates-list',
    description: 'Retrieve all available templates for creating researches',
    title: 'List Templates',
)]
#[InputSchema(class: ListTemplatesRequest::class)]
final readonly class ListTemplatesToolAction
{
    public function __construct(
        private LoggerInterface $logger,
        private TemplateServiceInterface $templateService,
    ) {}

    #[Post(path: '/tools/call/research-templates-list', name: 'tools.research-templates-list')]
    public function __invoke(ListTemplatesRequest $request): CallToolResult
    {
        $this->logger->info('Listing templates', [
            'has_filters' => $request->hasFilters(),
            'tag_filter' => $request->tag,
            'name_filter' => $request->nameContains,
            'include_details' => $request->includeDetails,
        ]);

        try {
            // Validate request
            $validationErrors = $request->validate();
            if (!empty($validationErrors)) {
                return ToolResult::validationError($validationErrors);
            }

            // Get all templates
            $allTemplates = $this->templateService->findAll();

            // Apply filters
            $filteredTemplates = $this->applyFilters($allTemplates, $request);

            $response = [
                'success' => true,
                'templates' => $filteredTemplates,
            ];

            $this->logger->info('Templates listed successfully', [
                'total_available' => \count($allTemplates),
                'filters_applied' => $request->hasFilters(),
            ]);

            return ToolResult::success($response);

        } catch (ResearchException $e) {
            $this->logger->error('Error listing research templates', [
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error($e->getMessage());

        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error listing templates', [
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error('Failed to list templates: ' . $e->getMessage());
        }
    }

    /**
     * Apply filters to templates array
     */
    private function applyFilters(array $templates, ListTemplatesRequest $request): array
    {
        if (!$request->hasFilters()) {
            return $templates;
        }

        return \array_filter($templates, static function ($template) use ($request) {
            // Filter by tag
            if ($request->tag !== null) {
                if (!\in_array($request->tag, $template->tags, true)) {
                    return false;
                }
            }

            // Filter by name (partial match, case insensitive)
            if ($request->nameContains !== null) {
                $searchTerm = \strtolower(\trim($request->nameContains));
                $templateName = \strtolower((string) $template->name);

                if (!\str_contains($templateName, $searchTerm)) {
                    return false;
                }
            }

            return true;
        });
    }
}
