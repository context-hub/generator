<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Registry;

use Butschster\ContextGenerator\Template\Template;

/**
 * Interface for template providers
 */
interface TemplateProviderInterface
{
    /**
     * Get all templates provided by this provider
     *
     * @return array<Template>
     */
    public function getTemplates(): array;

    /**
     * Get a specific template by name
     */
    public function getTemplate(string $name): ?Template;

    /**
     * Get the priority of this provider (higher = more important)
     */
    public function getPriority(): int;
}
