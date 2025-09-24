<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Detection;

use Butschster\ContextGenerator\Template\Registry\TemplateRegistry;

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
            $matchingCriteria = $template->matches($projectMetadata);
            $confidence = $matchingCriteria['confidence'];
            if ($confidence > 0.0) {
                $matches[] = new TemplateMatchResult(
                    template: $template,
                    confidence: $confidence,
                    matchingCriteria: $matchingCriteria,
                );
            }
        }

        return $matches;
    }
}
