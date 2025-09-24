<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\Storage;

use Butschster\ContextGenerator\Research\Domain\Model\Entry;
use Butschster\ContextGenerator\Research\Domain\Model\Research;
use Butschster\ContextGenerator\Research\Domain\ValueObject\EntryId;
use Butschster\ContextGenerator\Research\Domain\ValueObject\ResearchId;
use Butschster\ContextGenerator\Research\MCP\DTO\EntryCreateRequest;
use Butschster\ContextGenerator\Research\MCP\DTO\EntryUpdateRequest;
use Butschster\ContextGenerator\Research\MCP\DTO\ResearchCreateRequest;
use Butschster\ContextGenerator\Research\MCP\DTO\ResearchUpdateRequest;

interface StorageDriverInterface
{
    /**
     * Check if driver supports the specified storage type
     */
    public function supports(string $type): bool;

    /**
     * Create new research
     */
    public function createResearch(ResearchCreateRequest $request): Research;

    /**
     * Update existing research
     */
    public function updateResearch(ResearchId $researchId, ResearchUpdateRequest $request): Research;

    /**
     * Delete research and all its entries
     */
    public function deleteResearch(ResearchId $researchId): bool;

    /**
     * Create new entry in research
     */
    public function createEntry(ResearchId $researchId, EntryCreateRequest $request): Entry;

    /**
     * Update existing entry
     */
    public function updateEntry(ResearchId $researchId, EntryId $entryId, EntryUpdateRequest $request): Entry;

    /**
     * Delete entry from research
     */
    public function deleteEntry(ResearchId $researchId, EntryId $entryId): bool;

    /**
     * Get storage driver name
     */
    public function getName(): string;
}
