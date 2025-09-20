<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Detection;

use Butschster\ContextGenerator\Template\Template;

/**
 * Result of template detection process
 */
final readonly class TemplateDetectionResult
{
    public function __construct(
        public ?Template $template,
        public float $confidence,
        public string $detectionMethod, // 'template_criteria' or 'analyzer'
        public array $metadata = [],
    ) {}

    /**
     * Check if this result has high confidence (>= 0.8)
     * Note: This is different from the template detection threshold (0.9)
     */
    public function hasHighConfidence(): bool
    {
        return $this->confidence >= 0.8;
    }

    /**
     * Check if this result meets the very high confidence threshold for template detection (> 0.9)
     */
    public function meetsTemplateDetectionThreshold(): bool
    {
        return $this->confidence > 0.9;
    }

    /**
     * Check if a template was successfully detected
     */
    public function hasTemplate(): bool
    {
        return $this->template !== null;
    }

    /**
     * Get detection method as human-readable string
     */
    public function getDetectionMethodDescription(): string
    {
        return match ($this->detectionMethod) {
            'template_criteria' => 'Template Detection Criteria',
            'analyzer' => 'Project Analysis',
            default => 'Unknown',
        };
    }

    /**
     * Check if this was a high-confidence template detection
     */
    public function isHighConfidenceTemplateDetection(): bool
    {
        return $this->detectionMethod === 'template_criteria' && $this->meetsTemplateDetectionThreshold();
    }
}
