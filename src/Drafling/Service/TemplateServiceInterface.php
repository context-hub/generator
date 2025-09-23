<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Service;

use Butschster\ContextGenerator\Drafling\Domain\Model\Template;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\TemplateKey;

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
    public function getAllTemplates(): array;

    /**
     * Get a template by key
     *
     * @param TemplateKey $key
     * @return Template|null
     */
    public function getTemplate(TemplateKey $key): ?Template;

    /**
     * Check if template exists
     *
     * @param TemplateKey $key
     * @return bool
     */
    public function templateExists(TemplateKey $key): bool;

    /**
     * Resolve display name to internal category key
     * Checks both internal key and display name for matches
     *
     * @param Template $template
     * @param string $displayNameOrKey
     * @return string|null Internal category key or null if not found
     */
    public function resolveCategoryKey(Template $template, string $displayNameOrKey): ?string;

    /**
     * Resolve display name to internal entry type key
     * Checks both internal key and display name for matches
     *
     * @param Template $template
     * @param string $displayNameOrKey
     * @return string|null Internal entry type key or null if not found
     */
    public function resolveEntryTypeKey(Template $template, string $displayNameOrKey): ?string;

    /**
     * Resolve display name to internal status value
     * Checks both internal value and display name for matches
     *
     * @param Template $template
     * @param string $entryTypeKey Internal entry type key
     * @param string $displayNameOrValue
     * @return string|null Internal status value or null if not found
     */
    public function resolveStatusValue(Template $template, string $entryTypeKey, string $displayNameOrValue): ?string;

    /**
     * Get available statuses for an entry type
     *
     * @param Template $template
     * @param string $entryTypeKey
     * @return array Array of status values
     */
    public function getAvailableStatuses(Template $template, string $entryTypeKey): array;

    /**
     * Refresh templates from storage
     */
    public function refreshTemplates(): void;
}
