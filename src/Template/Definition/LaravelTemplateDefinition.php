<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Definition;

/**
 * Laravel project template definition using the improved abstract base
 */
final class LaravelTemplateDefinition extends AbstractTemplateDefinition
{
    public function getName(): string
    {
        return 'laravel';
    }

    public function getDescription(): string
    {
        return 'Laravel PHP Framework project template';
    }

    public function getTags(): array
    {
        return ['php', 'laravel', 'web', 'framework'];
    }

    public function getPriority(): int
    {
        return 100;
    }

    protected function getSourceDirectories(): array
    {
        return ['app', 'database', 'routes', 'config'];
    }

    protected function getFrameworkSpecificCriteria(): array
    {
        return [
            'files' => ['composer.json', 'artisan'],
            'patterns' => ['laravel/framework'],
        ];
    }

    /**
     * Override to customize Laravel tree view with framework-specific directories
     */
    #[\Override]
    protected function customizeTreeViewConfig(
        \Butschster\ContextGenerator\Lib\TreeBuilder\TreeViewConfig $config,
    ): \Butschster\ContextGenerator\Lib\TreeBuilder\TreeViewConfig {
        return new \Butschster\ContextGenerator\Lib\TreeBuilder\TreeViewConfig(
            showCharCount: true,
            includeFiles: true,
            maxDepth: 3,
            dirContext: [
                'app' => 'Application core - models, controllers, services',
                'database' => 'Database migrations, seeders, and factories',
                'routes' => 'Application route definitions',
                'config' => 'Application configuration files',
            ],
        );
    }

    /**
     * Add Laravel-specific documents beyond the basic structure
     */
    #[\Override]
    protected function createAdditionalDocuments(array $projectMetadata): array
    {
        $documents = [];

        // Add Controllers and Models document if they exist
        $existingDirs = $projectMetadata['existingDirectories'] ?? $projectMetadata['directories'] ?? [];

        if (\in_array('app', $existingDirs, true)) {
            $documents[] = new \Butschster\ContextGenerator\Document\Document(
                description: 'Laravel Controllers and Models',
                outputPath: 'docs/laravel-controllers-models.md',
                overwrite: true,
                modifiers: ['php-signature'],
                tags: ['laravel', 'controllers', 'models'],
                fileSource: new \Butschster\ContextGenerator\Source\File\FileSource(
                    sourcePaths: ['app/Http/Controllers', 'app/Models'],
                    description: 'Laravel Controllers and Models',
                    filePattern: '*.php',
                    modifiers: ['php-signature'],
                ),
            );
        }

        // Add Routes document if routes directory exists
        if (\in_array('routes', $existingDirs, true)) {
            $documents[] = new \Butschster\ContextGenerator\Document\Document(
                description: 'Laravel Routes Configuration',
                outputPath: 'docs/laravel-routes.md',
                overwrite: true,
                modifiers: [],
                tags: ['laravel', 'routes'],
                fileSource: new \Butschster\ContextGenerator\Source\File\FileSource(
                    sourcePaths: ['routes'],
                    description: 'Laravel Routes',
                    filePattern: '*.php',
                ),
            );
        }

        return $documents;
    }
}
