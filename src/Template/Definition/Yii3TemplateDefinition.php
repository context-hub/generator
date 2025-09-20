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
 * Yii3 PHP Framework project template definition
 */
final class Yii3TemplateDefinition implements TemplateDefinitionInterface
{
    /**
     * Yii3-specific directories that indicate a Yii3 project
     */
    private const array YII3_DIRECTORIES = [
        'src',
        'config',
        'resources',
        'public',
    ];

    /**
     * Yii3-specific files that indicate a Yii3 project
     */
    private const array YII3_FILES = [
        'yii',
    ];

    /**
     * Yii3-specific packages in composer.json
     */
    private const array YII3_PACKAGES = [
        'yiisoft/yii-web',
        'yiisoft/yii-console',
        'yiisoft/yii-runner-http',
        'yiisoft/yii-runner-console',
        'yiisoft/di',
        'yiisoft/config',
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
        return 'yii3';
    }

    public function getDescription(): string
    {
        return 'Yii3 PHP Framework project template';
    }

    public function getTags(): array
    {
        return ['php', 'yii3', 'framework', 'web', 'modern'];
    }

    public function getPriority(): int
    {
        return 92; // High priority for specific framework detection, slightly higher than Yii2
    }

    public function getDetectionCriteria(): array
    {
        return [
            'files' => \array_merge(['composer.json'], self::YII3_FILES),
            'patterns' => self::YII3_PACKAGES,
            'directories' => self::YII3_DIRECTORIES,
        ];
    }

    /**
     * Create the Yii3 project structure document
     */
    private function createStructureDocument(array $projectMetadata): Document
    {
        return new Document(
            description: 'Yii3 Project Structure',
            outputPath: 'docs/yii3-structure.md',
            overwrite: true,
            modifiers: [],
            tags: [],
            treeSource: new TreeSource(
                sourcePaths: ['src', 'config', 'resources', 'public'],
                description: 'Yii3 Directory Structure',
                treeView: new TreeViewConfig(
                    showCharCount: true,
                    includeFiles: true,
                    maxDepth: 3,
                ),
            ),
        );
    }
}
