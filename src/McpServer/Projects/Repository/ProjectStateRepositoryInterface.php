<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Projects\Repository;

use Butschster\ContextGenerator\McpServer\Projects\DTO\ProjectStateDTO;

interface ProjectStateRepositoryInterface
{
    /**
     * Load project state from storage
     */
    public function load(): ProjectStateDTO;

    /**
     * Save project state to storage
     */
    public function save(ProjectStateDTO $state): bool;
}
