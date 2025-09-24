<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Definition;

use Butschster\ContextGenerator\Document\Document;
use Butschster\ContextGenerator\Lib\TreeBuilder\TreeViewConfig;
use Butschster\ContextGenerator\Source\File\FileSource;

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
        TreeViewConfig $config,
    ): TreeViewConfig {
        return new TreeViewConfig(
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
            $documents[] = new Document(
                description: 'Laravel Controllers and Models',
                outputPath: 'docs/laravel-controllers-models.md',
                tags: ['laravel', 'controllers', 'models'],
                fileSource: new FileSource(
                    sourcePaths: ['app/Http/Controllers', 'app/Models'],
                    description: 'Laravel Controllers and Models',
                    filePattern: '*.php',
                ),
            );
        }

        // Add Routes document if routes directory exists
        if (\in_array('routes', $existingDirs, true)) {
            $documents[] = new Document(
                description: 'Laravel Routes Configuration',
                outputPath: 'docs/laravel-routes.md',
                tags: ['laravel', 'routes'],
                fileSource: new FileSource(
                    sourcePaths: ['routes'],
                    description: 'Laravel Routes',
                    filePattern: '*.php',
                ),
            );
        }

        return $documents;
    }
}
