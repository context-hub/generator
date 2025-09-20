<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Registry;

use Butschster\ContextGenerator\Template\Template;

/**
 * Registry for managing and accessing templates
 */
final class TemplateRegistry
{
    /** @var array<TemplateProviderInterface> */
    private array $providers = [];

    /**
     * Register a template provider
     */
    public function registerProvider(TemplateProviderInterface $provider): void
    {
        $this->providers[] = $provider;

        // Sort by priority (highest first)
        \usort($this->providers, static fn($a, $b) => $b->getPriority() <=> $a->getPriority());
    }

    /**
     * Get all available templates from all providers
     *
     * @return array<Template>
     */
    public function getAllTemplates(): array
    {
        $templates = [];
        $seen = [];

        foreach ($this->providers as $provider) {
            foreach ($provider->getTemplates() as $template) {
                // Avoid duplicates, prioritizing higher priority providers
                if (!isset($seen[$template->name])) {
                    $templates[] = $template;
                    $seen[$template->name] = true;
                }
            }
        }

        // Sort by priority (highest first)
        \usort($templates, static fn($a, $b) => $b->priority <=> $a->priority);

        return $templates;
    }

    /**
     * Get a specific template by name
     */
    public function getTemplate(string $name): ?Template
    {
        foreach ($this->providers as $provider) {
            $template = $provider->getTemplate($name);
            if ($template !== null) {
                return $template;
            }
        }

        return null;
    }
}
