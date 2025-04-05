<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\BinaryUpdater\Strategy;

/**
 * Interface for platform-specific binary update strategies.
 */
interface UpdateStrategyInterface
{
    /**
     * Update a binary file, handling the case where the file is currently in use.
     * This typically involves creating an external script that runs after the
     * current process exits.
     *
     * @return bool Whether the update process was started successfully
     */
    public function update(string $sourcePath, string $targetPath): bool;
}
