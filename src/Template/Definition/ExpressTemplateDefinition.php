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
 * Express.js project template definition
 */
final class ExpressTemplateDefinition implements TemplateDefinitionInterface
{
    /**
     * Express-specific directories that indicate an Express project
     */
    private const array EXPRESS_DIRECTORIES = [
        'routes',
        'middleware',
        'controllers',
        'models',
        'views',
        'public',
    ];

    /**
     * Express-specific packages in package.json
     */
    private const array EXPRESS_PACKAGES = [
        'express',
        'express-generator',
    ];

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
        return 'express';
    }

    public function getDescription(): string
    {
        return 'Express.js Node.js framework project template';
    }

    public function getTags(): array
    {
        return ['javascript', 'nodejs', 'express', 'backend', 'api'];
    }

    public function getPriority(): int
    {
        return 80; // Medium-high priority for backend framework
    }

    public function getDetectionCriteria(): array
    {
        return [
            'files' => ['package.json'],
            'patterns' => self::EXPRESS_PACKAGES,
            'directories' => self::EXPRESS_DIRECTORIES,
        ];
    }

    /**
     * Create the Express project structure document
     */
    private function createStructureDocument(array $projectMetadata): Document
    {
        return new Document(
            description: 'Express Project Structure',
            outputPath: 'docs/express-structure.md',
            overwrite: true,
            modifiers: [],
            tags: [],
            treeSource: new TreeSource(
                sourcePaths: ['routes', 'controllers', 'middleware', 'models'],
                description: 'Express Directory Structure',
                treeView: new TreeViewConfig(
                    showCharCount: true,
                    includeFiles: true,
                    maxDepth: 3,
                ),
            ),
        );
    }
}
