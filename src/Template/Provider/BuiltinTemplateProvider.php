<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Provider;

use Butschster\ContextGenerator\Template\Definition\TemplateDefinitionRegistry;
use Butschster\ContextGenerator\Template\Registry\TemplateProviderInterface;
use Butschster\ContextGenerator\Template\Template;

/**
 * Provides built-in templates using template definitions
 */
final readonly class BuiltinTemplateProvider implements TemplateProviderInterface
{
    public function __construct(
        private TemplateDefinitionRegistry $definitionRegistry,
    ) {}

    public function getTemplates(): array
    {
        // For the provider interface, we create templates without project metadata
        // The specific templates will be created with metadata when actually used
        return $this->definitionRegistry->createAllTemplates();
    }

    public function getTemplate(string $name): ?Template
    {
        return $this->definitionRegistry->createTemplate($name);
    }

    public function getPriority(): int
    {
        return 100; // High priority for built-in templates
    }
}
