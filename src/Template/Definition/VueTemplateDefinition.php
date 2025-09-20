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
 * Vue3 project template definition
 */
final class VueTemplateDefinition implements TemplateDefinitionInterface
{
    /**
     * Vue-specific directories that indicate a Vue project
     */
    private const array VUE_DIRECTORIES = [
        'src',
        'public',
        'components',
        'views',
        'assets',
    ];

    /**
     * Vue-specific packages in package.json
     */
    private const array VUE_PACKAGES = [
        'vue',
        '@vue/cli-service',
        'vite',
        '@vitejs/plugin-vue',
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
        return 'vue';
    }

    public function getDescription(): string
    {
        return 'Vue3 application project template';
    }

    public function getTags(): array
    {
        return ['javascript', 'vue', 'vue3', 'frontend', 'spa'];
    }

    public function getPriority(): int
    {
        return 85; // High priority for specific framework detection
    }

    public function getDetectionCriteria(): array
    {
        return [
            'files' => ['package.json'],
            'patterns' => self::VUE_PACKAGES,
            'directories' => self::VUE_DIRECTORIES,
        ];
    }

    /**
     * Create the Vue project structure document
     */
    private function createStructureDocument(array $projectMetadata): Document
    {
        return new Document(
            description: 'Vue Project Structure',
            outputPath: 'docs/vue-structure.md',
            overwrite: true,
            modifiers: [],
            tags: [],
            treeSource: new TreeSource(
                sourcePaths: ['src', 'public'],
                description: 'Vue Directory Structure',
                treeView: new TreeViewConfig(
                    showCharCount: true,
                    includeFiles: true,
                    maxDepth: 3,
                ),
            ),
        );
    }
}
