<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Service;

use Butschster\ContextGenerator\Drafling\Domain\Model\Entry;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\EntryId;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\ProjectId;
use Butschster\ContextGenerator\Drafling\MCP\DTO\EntryCreateRequest;
use Butschster\ContextGenerator\Drafling\MCP\DTO\EntryUpdateRequest;

/**
 * Service interface for entry operations
 */
interface EntryServiceInterface
{
    /**
     * Create a new entry in the specified project
     *
     * @throws \Butschster\ContextGenerator\Drafling\Exception\ProjectNotFoundException
     * @throws \Butschster\ContextGenerator\Drafling\Exception\TemplateNotFoundException
     * @throws \Butschster\ContextGenerator\Drafling\Exception\DraflingException
     */
    public function createEntry(ProjectId $projectId, EntryCreateRequest $request): Entry;

    /**
     * Update an existing entry
     *
     * @throws \Butschster\ContextGenerator\Drafling\Exception\ProjectNotFoundException
     * @throws \Butschster\ContextGenerator\Drafling\Exception\EntryNotFoundException
     * @throws \Butschster\ContextGenerator\Drafling\Exception\DraflingException
     */
    public function updateEntry(ProjectId $projectId, EntryId $entryId, EntryUpdateRequest $request): Entry;

    /**
     * Check if an entry exists
     */
    public function entryExists(ProjectId $projectId, EntryId $entryId): bool;

    /**
     * Get entries for a project with optional filtering
     *
     * @return Entry[]
     */
    public function getEntries(ProjectId $projectId, array $filters = []): array;

    /**
     * Delete an entry
     *
     */
    public function deleteEntry(ProjectId $projectId, EntryId $entryId): bool;
}
