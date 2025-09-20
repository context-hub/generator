<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Definition;

use Butschster\ContextGenerator\Config\Registry\ConfigRegistry;
use Butschster\ContextGenerator\Document\Document;
use Butschster\ContextGenerator\Document\DocumentRegistry;
use Butschster\ContextGenerator\Lib\TreeBuilder\TreeViewConfig;
use Butschster\ContextGenerator\Source\Tree\TreeSource;
use Butschster\ContextGenerator\Template\Template;

/**
 * Laravel project template definition
 */
final class LaravelTemplateDefinition implements TemplateDefinitionInterface
{
    public function createTemplate(array $projectMetadata = []): Template
    {
        $config = new ConfigRegistry();

        $documents = new DocumentRegistry([
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

    public function getDetectionCriteria(): array
    {
        return [
            'files' => ['composer.json', 'artisan'],
            'patterns' => ['laravel/framework'],
            'directories' => ['app', 'database', 'routes'],
        ];
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
}
