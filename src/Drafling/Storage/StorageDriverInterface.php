<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Storage;

use Butschster\ContextGenerator\Drafling\Domain\Model\Entry;
use Butschster\ContextGenerator\Drafling\Domain\Model\Project;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\EntryId;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\ProjectId;
use Butschster\ContextGenerator\Drafling\MCP\DTO\EntryCreateRequest;
use Butschster\ContextGenerator\Drafling\MCP\DTO\EntryUpdateRequest;
use Butschster\ContextGenerator\Drafling\MCP\DTO\ProjectCreateRequest;
use Butschster\ContextGenerator\Drafling\MCP\DTO\ProjectUpdateRequest;

/**
 * Storage driver contract for different persistence mechanisms
 *
 * @template TConfig of object
 */
interface StorageDriverInterface
{
    /**
     * Check if driver supports the specified storage type
     */
    public function supports(string $type): bool;

    /**
     * Initialize storage driver with configuration
     *
     * @param TConfig $config
     */
    public function initialize(object $config): void;

    /**
     * Create new project
     */
    public function createProject(ProjectCreateRequest $request): Project;

    /**
     * Update existing project
     */
    public function updateProject(ProjectId $projectId, ProjectUpdateRequest $request): Project;

    /**
     * Delete project and all its entries
     */
    public function deleteProject(ProjectId $projectId): bool;

    /**
     * Create new entry in project
     */
    public function createEntry(ProjectId $projectId, EntryCreateRequest $request): Entry;

    /**
     * Update existing entry
     */
    public function updateEntry(ProjectId $projectId, EntryId $entryId, EntryUpdateRequest $request): Entry;

    /**
     * Delete entry from project
     */
    public function deleteEntry(ProjectId $projectId, EntryId $entryId): bool;

    /**
     * Synchronize storage state with external changes
     */
    public function synchronize(): void;

    /**
     * Get storage driver name
     */
    public function getName(): string;
}
