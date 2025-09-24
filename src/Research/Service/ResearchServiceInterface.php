<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\Service;

use Butschster\ContextGenerator\Research\Domain\Model\Research;
use Butschster\ContextGenerator\Research\Domain\ValueObject\ResearchId;
use Butschster\ContextGenerator\Research\MCP\DTO\ResearchCreateRequest;
use Butschster\ContextGenerator\Research\MCP\DTO\ResearchUpdateRequest;

/**
 * Service interface for research operations
 */
interface ResearchServiceInterface
{
    /**
     * Create a new research from template
     *
     * @throws \Butschster\ContextGenerator\Research\Exception\TemplateNotFoundException
     * @throws \Butschster\ContextGenerator\Research\Exception\ResearchException
     */
    public function create(ResearchCreateRequest $request): Research;

    /**
     * Update an existing research
     *
     * @throws \Butschster\ContextGenerator\Research\Exception\ResearchNotFoundException
     * @throws \Butschster\ContextGenerator\Research\Exception\ResearchException
     */
    public function update(ResearchId $researchId, ResearchUpdateRequest $request): Research;

    /**
     * Check if a research exists
     */
    public function exists(ResearchId $researchId): bool;

    /**
     * Get a single research by ID
     *
     */
    public function get(ResearchId $researchId): ?Research;

    /**
     * List researches with optional filtering
     *
     * @return Research[]
     */
    public function findAll(array $filters = []): array;

    /**
     * Delete a research
     *
     */
    public function delete(ResearchId $researchId): bool;

    /**
     * Add a memory entry to research
     *
     * @throws \Butschster\ContextGenerator\Research\Exception\ResearchNotFoundException
     * @throws \Butschster\ContextGenerator\Research\Exception\ResearchException
     */
    public function addMemory(ResearchId $researchId, string $memory): Research;
}
