<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\MCP\Tools;

use Butschster\ContextGenerator\Research\Domain\ValueObject\ResearchId;
use Butschster\ContextGenerator\Research\Exception\ResearchException;
use Butschster\ContextGenerator\Research\Exception\ResearchNotFoundException;
use Butschster\ContextGenerator\Research\MCP\DTO\ResearchUpdateRequest;
use Butschster\ContextGenerator\Research\Service\ResearchServiceInterface;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'research-update',
    description: 'Update existing research properties including title, description, status, tags, entry directories, and memory entries',
    title: 'Update Research',
)]
#[InputSchema(class: ResearchUpdateRequest::class)]
final readonly class UpdateResearchToolAction
{
    public function __construct(
        private LoggerInterface $logger,
        private ResearchServiceInterface $service,
    ) {}

    #[Post(path: '/tools/call/research-update', name: 'tools.research-update')]
    public function __invoke(ResearchUpdateRequest $request): CallToolResult
    {
        $this->logger->info('Updating research', [
            'research_id' => $request->researchId,
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

            $researchId = ResearchId::fromString($request->researchId);
            if (!$this->service->exists($researchId)) {
                return ToolResult::error("Research '{$request->researchId}' not found");
            }

            $research = $this->service->update($researchId, $request);

            $this->logger->info('Research updated successfully', [
                'research_id' => $request->researchId,
                'title' => $research->name,
                'status' => $research->status,
            ]);

            return ToolResult::success([
                'success' => true,
            ]);

        } catch (ResearchNotFoundException $e) {
            $this->logger->error('Research not found', [
                'research_id' => $request->researchId,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error($e->getMessage());

        } catch (ResearchException $e) {
            $this->logger->error('Error during research update', [
                'research_id' => $request->researchId,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error($e->getMessage());

        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error updating research', [
                'research_id' => $request->researchId,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error('Failed to update research: ' . $e->getMessage());
        }
    }

    /**
     * Get list of changes applied based on the request
     */
    private function getAppliedChanges(ResearchUpdateRequest $request): array
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
