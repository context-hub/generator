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
 * Yii2 PHP Framework project template definition
 */
final class Yii2TemplateDefinition implements TemplateDefinitionInterface
{
    /**
     * Yii2-specific directories that indicate a Yii2 project
     */
    private const array YII2_DIRECTORIES = [
        'controllers',
        'models',
        'views',
        'web',
        'config',
    ];

    /**
     * Yii2-specific files that indicate a Yii2 project
     */
    private const array YII2_FILES = [
        'yii',
        'requirements.php',
    ];

    /**
     * Yii2-specific packages in composer.json
     */
    private const array YII2_PACKAGES = [
        'yiisoft/yii2',
        'yiisoft/yii2-app-basic',
        'yiisoft/yii2-app-advanced',
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
        return 'yii2';
    }

    public function getDescription(): string
    {
        return 'Yii2 PHP Framework project template';
    }

    public function getTags(): array
    {
        return ['php', 'yii2', 'framework', 'web'];
    }

    public function getPriority(): int
    {
        return 90; // High priority for specific framework detection
    }

    public function getDetectionCriteria(): array
    {
        return [
            'files' => \array_merge(['composer.json'], self::YII2_FILES),
            'patterns' => self::YII2_PACKAGES,
            'directories' => self::YII2_DIRECTORIES,
        ];
    }

    /**
     * Create the Yii2 project structure document
     */
    private function createStructureDocument(array $projectMetadata): Document
    {
        return new Document(
            description: 'Yii2 Project Structure',
            outputPath: 'docs/yii2-structure.md',
            overwrite: true,
            modifiers: [],
            tags: [],
            treeSource: new TreeSource(
                sourcePaths: ['controllers', 'models', 'views', 'web', 'config'],
                description: 'Yii2 Directory Structure',
                treeView: new TreeViewConfig(
                    showCharCount: true,
                    includeFiles: true,
                    maxDepth: 3,
                ),
            ),
        );
    }
}
