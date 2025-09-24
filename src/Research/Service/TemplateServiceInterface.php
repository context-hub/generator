<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\Service;

use Butschster\ContextGenerator\Research\Domain\Model\Template;
use Butschster\ContextGenerator\Research\Domain\ValueObject\TemplateKey;

/**
 * Service interface for template operations
 */
interface TemplateServiceInterface
{
    /**
     * Get all available templates
     *
     * @return Template[]
     */
    public function findAll(): array;

    /**
     * Get a template by key
     *
     */
    public function getTemplate(TemplateKey $key): ?Template;

    /**
     * Check if template exists
     *
     */
    public function templateExists(TemplateKey $key): bool;

    /**
     * Resolve display name to internal category key
     * Checks both internal key and display name for matches
     *
     * @return string|null Internal category key or null if not found
     */
    public function resolveCategoryKey(Template $template, string $displayNameOrKey): ?string;

    /**
     * Resolve display name to internal entry type key
     * Checks both internal key and display name for matches
     *
     * @return string|null Internal entry type key or null if not found
     */
    public function resolveEntryTypeKey(Template $template, string $displayNameOrKey): ?string;

    /**
     * Resolve display name to internal status value
     * Checks both internal value and display name for matches
     *
     * @param string $entryTypeKey Internal entry type key
     * @return string|null Internal status value or null if not found
     */
    public function resolveStatusValue(Template $template, string $entryTypeKey, string $displayNameOrValue): ?string;

    /**
     * Get available statuses for an entry type
     *
     * @return array Array of status values
     */
    public function getAvailableStatuses(Template $template, string $entryTypeKey): array;

    /**
     * Refresh templates from storage
     */
    public function refreshTemplates(): void;
}
