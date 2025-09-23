<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Service;

use Butschster\ContextGenerator\Drafling\Domain\Model\Template;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\TemplateKey;

/**
 * Template service contract for managing template operations
 */
interface TemplateServiceInterface
{
    /**
     * Get all available templates
     *
     * @return Template[]
     */
    public function getAllTemplates(): array;

    /**
     * Get template by key
     */
    public function getTemplate(TemplateKey $key): ?Template;

    /**
     * Check if template exists
     */
    public function templateExists(TemplateKey $key): bool;

    /**
     * Validate template configuration
     *
     * @param array $templateData Raw template data
     * @return array Array of validation errors (empty if valid)
     */
    public function validateTemplate(array $templateData): array;
}
