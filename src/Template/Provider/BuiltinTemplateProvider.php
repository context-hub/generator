<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Provider;

use Butschster\ContextGenerator\Template\Definition\TemplateDefinitionRegistry;
use Butschster\ContextGenerator\Template\Registry\TemplateProviderInterface;
use Butschster\ContextGenerator\Template\Template;
use Butschster\ContextGenerator\Template\TemplateFactory;

/**
 * Provides built-in templates using template definitions
 */
final readonly class BuiltinTemplateProvider implements TemplateProviderInterface
{
    private TemplateDefinitionRegistry $definitionRegistry;

    public function __construct()
    {
        $this->definitionRegistry = TemplateFactory::getDefinitionRegistry();
    }

    #[\Override]
    public function getTemplates(): array
    {
        // For the provider interface, we create templates without project metadata
        // The specific templates will be created with metadata when actually used
        return $this->definitionRegistry->createAllTemplates();
    }

    #[\Override]
    public function getTemplate(string $name): ?Template
    {
        return $this->definitionRegistry->createTemplate($name);
    }

    /**
     * Get template with project metadata for context-aware creation
     *
     * @param array<string, mixed> $projectMetadata
     */
    public function getTemplateWithMetadata(string $name, array $projectMetadata = []): ?Template
    {
        return $this->definitionRegistry->createTemplate($name, $projectMetadata);
    }

    /**
     * Get all templates with project metadata for context-aware creation
     *
     * @param array<string, mixed> $projectMetadata
     * @return array<Template>
     */
    public function getTemplatesWithMetadata(array $projectMetadata = []): array
    {
        return $this->definitionRegistry->createAllTemplates($projectMetadata);
    }

    #[\Override]
    public function getPriority(): int
    {
        return 100; // High priority for built-in templates
    }

    /**
     * Get the underlying definition registry
     */
    public function getDefinitionRegistry(): TemplateDefinitionRegistry
    {
        return $this->definitionRegistry;
    }
}
