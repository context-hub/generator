<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\ConfigLoader\Import;

/**
 * Detects circular dependencies in imports
 */
final class CircularImportDetector
{
    /**
     * @var array<string> Stack of import paths being processed
     */
    private array $importStack = [];

    /**
     * Check if adding this path would create a circular dependency
     */
    public function wouldCreateCircularDependency(string $path): bool
    {
        return \in_array($path, $this->importStack, true);
    }

    /**
     * Begin processing an import path
     */
    public function beginProcessing(string $path): void
    {
        if ($this->wouldCreateCircularDependency($path)) {
            throw new \RuntimeException(
                \sprintf(
                    'Circular import detected: %s is already being processed. Import stack: %s',
                    $path,
                    \implode(' -> ', $this->importStack),
                ),
            );
        }

        $this->importStack[] = $path;
    }

    /**
     * Finish processing an import path
     */
    public function endProcessing(string $path): void
    {
        // Find the path in the stack and remove it and anything after it
        $index = \array_search($path, $this->importStack, true);

        if ($index !== false) {
            \array_splice($this->importStack, $index);
        }
    }
}
