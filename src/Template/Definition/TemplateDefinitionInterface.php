<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Definition;

use Butschster\ContextGenerator\Template\Template;

/**
 * Interface for template definitions
 */
interface TemplateDefinitionInterface
{
    /**
     * Create the template instance
     *
     * @param array<string, mixed> $projectMetadata Optional project metadata for context-aware template creation
     */
    public function createTemplate(array $projectMetadata = []): Template;

    /**
     * Get the template name/identifier
     */
    public function getName(): string;

    /**
     * Get the template description
     */
    public function getDescription(): string;

    /**
     * Get template tags for categorization
     *
     * @return array<string>
     */
    public function getTags(): array;

    /**
     * Get the template priority (higher = more preferred)
     */
    public function getPriority(): int;

    /**
     * Get detection criteria for automatic selection
     *
     * @return array<string, mixed>
     */
    public function getDetectionCriteria(): array;
}
