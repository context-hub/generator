<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template;

use Butschster\ContextGenerator\Template\Definition\GenericPhpTemplateDefinition;
use Butschster\ContextGenerator\Template\Definition\LaravelTemplateDefinition;
use Butschster\ContextGenerator\Template\Definition\TemplateDefinitionRegistry;

/**
 * Factory for creating built-in templates using template definitions
 */
final class TemplateFactory
{
    private static ?TemplateDefinitionRegistry $registry = null;

    /**
     * Get the template definition registry with all built-in definitions
     */
    public static function getDefinitionRegistry(): TemplateDefinitionRegistry
    {
        if (self::$registry === null) {
            self::$registry = new TemplateDefinitionRegistry([
                new LaravelTemplateDefinition(),
                new GenericPhpTemplateDefinition(),
            ]);
        }

        return self::$registry;
    }

    /**
     * Create the Laravel template
     *
     * @param array<string, mixed> $projectMetadata
     */
    public static function createLaravelTemplate(array $projectMetadata = []): Template
    {
        $definition = self::getDefinitionRegistry()->getDefinition('laravel');

        if ($definition === null) {
            throw new \RuntimeException('Laravel template definition not found');
        }

        return $definition->createTemplate($projectMetadata);
    }

    /**
     * Create the generic PHP template
     *
     * @param array<string, mixed> $projectMetadata
     */
    public static function createGenericPhpTemplate(array $projectMetadata = []): Template
    {
        $definition = self::getDefinitionRegistry()->getDefinition('generic-php');

        if ($definition === null) {
            throw new \RuntimeException('Generic PHP template definition not found');
        }

        return $definition->createTemplate($projectMetadata);
    }

    /**
     * Get all built-in templates
     *
     * @param array<string, mixed> $projectMetadata
     * @return array<Template>
     */
    public static function getAllBuiltinTemplates(array $projectMetadata = []): array
    {
        return self::getDefinitionRegistry()->createAllTemplates($projectMetadata);
    }

    /**
     * Create a template by name
     *
     * @param array<string, mixed> $projectMetadata
     */
    public static function createTemplate(string $name, array $projectMetadata = []): ?Template
    {
        return self::getDefinitionRegistry()->createTemplate($name, $projectMetadata);
    }

    /**
     * Get all available template names
     *
     * @return array<string>
     */
    public static function getAvailableTemplateNames(): array
    {
        return self::getDefinitionRegistry()->getTemplateNames();
    }
}
