<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\ConfigLoader\Import;

/**
 * Interface for detecting circular dependencies in imports
 */
interface CircularImportDetectorInterface
{
    /**
     * Check if adding this path would create a circular dependency
     */
    public function wouldCreateCircularDependency(string $path): bool;

    /**
     * Begin processing an import path
     */
    public function beginProcessing(string $path): void;

    /**
     * Finish processing an import path
     */
    public function endProcessing(string $path): void;
}
