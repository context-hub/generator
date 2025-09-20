<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Detection\Strategy;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\Template\Detection\TemplateDetectionResult;

/**
 * Strategy interface for template detection approaches
 */
interface TemplateDetectionStrategy
{
    /**
     * Attempt to detect a template for the given project
     */
    public function detect(FSPath $projectRoot, array $projectMetadata): ?TemplateDetectionResult;

    /**
     * Get the confidence threshold this strategy requires to be considered valid
     */
    public function getConfidenceThreshold(): float;

    /**
     * Get the priority of this strategy (higher = runs first)
     */
    public function getPriority(): int;

    /**
     * Get the name of this detection strategy
     */
    public function getName(): string;
}
