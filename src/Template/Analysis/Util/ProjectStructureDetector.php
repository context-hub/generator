<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Analysis\Util;

use Butschster\ContextGenerator\Application\FSPath;

/**
 * Utility for detecting common project directory structures
 */
final class ProjectStructureDetector
{
    /**
     * Common directories that indicate a project structure
     */
    private const array COMMON_DIRECTORIES = [
        'src',
        'app',
        'tests',
        'lib',
        'config',
    ];

    /**
     * Check which common directories exist in the project
     *
     * @return array<string> List of existing directories
     */
    public function detectExistingDirectories(FSPath $projectRoot): array
    {
        $existing = [];

        foreach (self::COMMON_DIRECTORIES as $directory) {
            if ($projectRoot->join($directory)->exists()) {
                $existing[] = $directory;
            }
        }

        return $existing;
    }

    /**
     * Calculate confidence score based on directory structure
     * More directories = higher confidence that this is a real project
     */
    public function calculateStructureConfidence(array $existingDirectories): float
    {
        $count = \count($existingDirectories);

        if ($count === 0) {
            return 0.1; // Very low confidence
        }

        if ($count === 1) {
            return 0.3; // Low confidence
        }

        if ($count === 2) {
            return 0.5; // Medium confidence
        }

        if ($count >= 3) {
            return 0.7; // High confidence
        }

        return 0.4; // Fallback
    }

    /**
     * Check if directory structure matches a specific pattern
     */
    public function matchesPattern(array $existingDirectories, array $requiredDirectories): bool
    {
        foreach ($requiredDirectories as $required) {
            if (!\in_array($required, $existingDirectories, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get confidence score for matching a specific pattern
     */
    public function getPatternMatchConfidence(array $existingDirectories, array $requiredDirectories): float
    {
        if (empty($requiredDirectories)) {
            return 0.0;
        }

        $matches = \count(\array_intersect($existingDirectories, $requiredDirectories));
        $total = \count($requiredDirectories);

        return $matches / $total;
    }
}
