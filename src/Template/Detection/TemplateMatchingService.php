<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Detection;

use Butschster\ContextGenerator\Template\Registry\TemplateRegistry;
use Butschster\ContextGenerator\Template\Template;

/**
 * Service for matching templates against project metadata using detection criteria
 */
final readonly class TemplateMatchingService
{
    public function __construct(
        private TemplateRegistry $templateRegistry,
    ) {}

    /**
     * Match all templates against project metadata and return results with confidence scores
     *
     * @param array<string, mixed> $projectMetadata
     * @return array<TemplateMatchResult>
     */
    public function matchTemplates(array $projectMetadata): array
    {
        $matches = [];
        $templates = $this->templateRegistry->getAllTemplates();

        foreach ($templates as $template) {
            $confidence = $this->calculateTemplateConfidence($template, $projectMetadata);
            if ($confidence > 0.0) {
                $matches[] = new TemplateMatchResult(
                    template: $template,
                    confidence: $confidence,
                    matchingCriteria: $this->getMatchingCriteria($template, $projectMetadata),
                );
            }
        }

        return $matches;
    }

    /**
     * Calculate confidence score for a template match
     */
    private function calculateTemplateConfidence(Template $template, array $projectMetadata): float
    {
        if (empty($template->detectionCriteria)) {
            return 0.0;
        }

        $totalCriteria = 0;
        $matchedCriteria = 0;
        $confidence = 0.0;

        // Check file criteria
        if (isset($template->detectionCriteria['files'])) {
            $files = $template->detectionCriteria['files'];
            $totalCriteria += \count($files);

            foreach ($files as $file) {
                if ($this->hasFile($projectMetadata, $file)) {
                    $matchedCriteria++;
                    $confidence += 0.3; // Files are important indicators
                }
            }
        }

        // Check directory criteria
        if (isset($template->detectionCriteria['directories'])) {
            $directories = $template->detectionCriteria['directories'];
            $totalCriteria += \count($directories);

            foreach ($directories as $directory) {
                if ($this->hasDirectory($projectMetadata, $directory)) {
                    $matchedCriteria++;
                    $confidence += 0.2; // Directories are moderate indicators
                }
            }
        }

        // Check package pattern criteria (composer.json or package.json)
        if (isset($template->detectionCriteria['patterns'])) {
            $patterns = $template->detectionCriteria['patterns'];
            $totalCriteria += \count($patterns);

            foreach ($patterns as $pattern) {
                if ($this->hasPackagePattern($projectMetadata, $pattern)) {
                    $matchedCriteria++;
                    $confidence += 0.4; // Package patterns are strong indicators
                }
            }
        }

        // Normalize confidence based on how many criteria were met
        if ($totalCriteria > 0) {
            $matchRatio = $matchedCriteria / $totalCriteria;
            $confidence = $confidence * (float) $matchRatio;
        }

        return \min($confidence, 1.0);
    }

    /**
     * Get which criteria matched for a template
     */
    private function getMatchingCriteria(Template $template, array $projectMetadata): array
    {
        $matching = [];

        // Check files
        if (isset($template->detectionCriteria['files'])) {
            foreach ($template->detectionCriteria['files'] as $file) {
                if ($this->hasFile($projectMetadata, $file)) {
                    $matching['files'][] = $file;
                }
            }
        }

        // Check directories
        if (isset($template->detectionCriteria['directories'])) {
            foreach ($template->detectionCriteria['directories'] as $directory) {
                if ($this->hasDirectory($projectMetadata, $directory)) {
                    $matching['directories'][] = $directory;
                }
            }
        }

        // Check patterns
        if (isset($template->detectionCriteria['patterns'])) {
            foreach ($template->detectionCriteria['patterns'] as $pattern) {
                if ($this->hasPackagePattern($projectMetadata, $pattern)) {
                    $matching['patterns'][] = $pattern;
                }
            }
        }

        return $matching;
    }

    /**
     * Check if project has a specific file
     */
    private function hasFile(array $projectMetadata, string $file): bool
    {
        return isset($projectMetadata['files']) && \in_array($file, $projectMetadata['files'], true);
    }

    /**
     * Check if project has a specific directory
     */
    private function hasDirectory(array $projectMetadata, string $directory): bool
    {
        return isset($projectMetadata['directories']) && \in_array($directory, $projectMetadata['directories'], true);
    }

    /**
     * Check if project has a specific package pattern in composer.json or package.json
     */
    private function hasPackagePattern(array $projectMetadata, string $pattern): bool
    {
        // Check composer.json packages
        if (isset($projectMetadata['composer']['packages'])) {
            if (\array_key_exists($pattern, $projectMetadata['composer']['packages'])) {
                return true;
            }
        }

        // Check package.json dependencies
        if (isset($projectMetadata['packageJson']['dependencies'])) {
            if (\array_key_exists($pattern, $projectMetadata['packageJson']['dependencies'])) {
                return true;
            }
        }

        return false;
    }
}
