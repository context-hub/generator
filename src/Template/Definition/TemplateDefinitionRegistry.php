<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Definition;

use Butschster\ContextGenerator\Template\Template;

/**
 * Registry for managing template definitions
 */
final class TemplateDefinitionRegistry
{
    /** @var array<TemplateDefinitionInterface> */
    private array $definitions = [];

    /**
     * @param array<TemplateDefinitionInterface> $definitions
     */
    public function __construct(array $definitions = [])
    {
        foreach ($definitions as $definition) {
            $this->registerDefinition($definition);
        }
    }

    /**
     * Register a template definition
     */
    public function registerDefinition(TemplateDefinitionInterface $definition): void
    {
        $this->definitions[] = $definition;

        // Sort by priority (highest first)
        \usort($this->definitions, static fn($a, $b) => $b->getPriority() <=> $a->getPriority());
    }

    /**
     * Get a specific template definition by name
     */
    public function getDefinition(string $name): ?TemplateDefinitionInterface
    {
        foreach ($this->definitions as $definition) {
            if ($definition->getName() === $name) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * Create all templates from registered definitions
     *
     * @param array<string, mixed> $projectMetadata
     * @return array<Template>
     */
    public function createAllTemplates(array $projectMetadata = []): array
    {
        $templates = [];

        foreach ($this->definitions as $definition) {
            $templates[] = $definition->createTemplate($projectMetadata);
        }

        return $templates;
    }

    /**
     * Create a specific template by name
     *
     * @param array<string, mixed> $projectMetadata
     */
    public function createTemplate(string $name, array $projectMetadata = []): ?Template
    {
        $definition = $this->getDefinition($name);

        return $definition?->createTemplate($projectMetadata);
    }
}
