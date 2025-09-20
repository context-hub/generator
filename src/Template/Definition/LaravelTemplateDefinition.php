<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Definition;

use Butschster\ContextGenerator\Config\Registry\ConfigRegistry;
use Butschster\ContextGenerator\Document\Document;
use Butschster\ContextGenerator\Document\DocumentRegistry;
use Butschster\ContextGenerator\Lib\TreeBuilder\TreeViewConfig;
use Butschster\ContextGenerator\Source\Tree\TreeSource;
use Butschster\ContextGenerator\Source\Text\TextSource;
use Butschster\ContextGenerator\Template\Template;

/**
 * Laravel project template definition
 */
final class LaravelTemplateDefinition implements TemplateDefinitionInterface
{
    #[\Override]
    public function createTemplate(array $projectMetadata = []): Template
    {
        $config = new ConfigRegistry();

        $documents = new DocumentRegistry([
            $this->createOverviewDocument($projectMetadata),
            $this->createStructureDocument($projectMetadata),
        ]);

        $config->register($documents);

        return new Template(
            name: $this->getName(),
            description: $this->getDescription(),
            tags: $this->getTags(),
            priority: $this->getPriority(),
            detectionCriteria: $this->getDetectionCriteria(),
            config: $config,
        );
    }

    #[\Override]
    public function getName(): string
    {
        return 'laravel';
    }

    #[\Override]
    public function getDescription(): string
    {
        return 'Laravel PHP Framework project template';
    }

    #[\Override]
    public function getTags(): array
    {
        return ['php', 'laravel', 'web', 'framework'];
    }

    #[\Override]
    public function getPriority(): int
    {
        return 100;
    }

    #[\Override]
    public function getDetectionCriteria(): array
    {
        return [
            'files' => ['composer.json', 'artisan'],
            'patterns' => ['laravel/framework'],
            'directories' => ['app', 'database', 'routes'],
        ];
    }

    /**
     * Create the Laravel application overview document
     */
    private function createOverviewDocument(array $projectMetadata): Document
    {
        return new Document(
            description: 'Laravel Application Overview',
            outputPath: 'docs/laravel-overview.md',
            overwrite: true,
            modifiers: [],
            tags: [],
            textSource: new TextSource(
                content: $this->generateOverviewContent($projectMetadata),
                description: 'Laravel Overview Header',
            ),
        );
    }

    /**
     * Create the Laravel project structure document
     */
    private function createStructureDocument(array $projectMetadata): Document
    {
        return new Document(
            description: 'Laravel Project Structure',
            outputPath: 'docs/laravel-structure.md',
            overwrite: true,
            modifiers: [],
            tags: [],
            treeSource: new TreeSource(
                sourcePaths: ['app', 'database', 'routes', 'config'],
                description: 'Laravel Directory Structure',
                treeView: new TreeViewConfig(
                    showCharCount: true,
                    includeFiles: true,
                    maxDepth: 3,
                ),
            ),
        );
    }

    /**
     * Generate overview content with Laravel-specific information
     */
    private function generateOverviewContent(array $projectMetadata): string
    {
        $composer = $projectMetadata['composer'] ?? null;
        $laravelVersion = $projectMetadata['laravelVersion'] ?? null;

        $content = <<<'CONTENT'
# Laravel Application Context

This document provides a comprehensive overview of the Laravel application structure and key components.

CONTENT;

        // Add Laravel version information if available
        if ($laravelVersion !== null) {
            $content .= "**Laravel Version:** `{$laravelVersion}`\n\n";
        }

        $content .= <<<'CONTENT'
## Project Structure

This Laravel application follows the standard Laravel directory structure:

- **app/**: Contains the core application code
  - **Http/Controllers/**: Request handlers
  - **Models/**: Eloquent models
  - **Services/**: Business logic services
  - **Providers/**: Service providers

- **database/**: Database related files
  - **migrations/**: Database migrations
  - **seeders/**: Database seeders
  - **factories/**: Model factories

- **routes/**: Route definitions
  - **web.php**: Web routes
  - **api.php**: API routes

- **config/**: Configuration files
- **resources/**: Views, assets, and language files
- **storage/**: Application storage
- **tests/**: Test files

CONTENT;

        // Add composer information if available
        if ($composer !== null) {
            $content .= "\n## Dependencies\n\n";

            if (isset($composer['name'])) {
                $content .= "**Package Name:** `{$composer['name']}`\n\n";
            }

            if (isset($composer['description'])) {
                $content .= "**Description:** {$composer['description']}\n\n";
            }
        }

        $content .= "\nGenerated automatically by CTX for Laravel projects.\n";

        return $content;
    }
}
