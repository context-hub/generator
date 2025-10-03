<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import;

/**
 * Detects circular dependencies in imports
 */
final class CircularImportDetector implements CircularImportDetectorInterface
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
        return \in_array(needle: $path, haystack: $this->importStack, strict: true);
    }

    /**
     * Begin processing an import path
     */
    public function beginProcessing(string $path): void
    {
        if ($this->wouldCreateCircularDependency(path: $path)) {
            throw new \RuntimeException(
                message: \sprintf(
                    'Circular import detected: %s is already being processed. Import stack: %s',
                    $path,
                    \implode(separator: ' -> ', array: $this->importStack),
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
        $index = \array_search(needle: $path, haystack: $this->importStack, strict: true);

        if ($index !== false) {
            \array_splice(array: $this->importStack, offset: $index);
        }
    }
}
