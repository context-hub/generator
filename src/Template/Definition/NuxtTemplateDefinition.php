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
 * Nuxt.js project template definition
 */
final class NuxtTemplateDefinition implements TemplateDefinitionInterface
{
    /**
     * Nuxt-specific directories that indicate a Nuxt project
     */
    private const array NUXT_DIRECTORIES = [
        'pages',
        'components',
        'layouts',
        'plugins',
        'middleware',
        'assets',
        'static',
        'public',
    ];

    /**
     * Nuxt-specific packages in package.json
     */
    private const array NUXT_PACKAGES = [
        'nuxt',
        '@nuxt/kit',
        'nuxt3',
        '@nuxt/cli',
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
        return 'nuxt';
    }

    public function getDescription(): string
    {
        return 'Nuxt.js Vue framework project template';
    }

    public function getTags(): array
    {
        return ['javascript', 'vue', 'nuxt', 'fullstack', 'ssr'];
    }

    public function getPriority(): int
    {
        return 88; // Higher priority than Vue, as Nuxt is more specific
    }

    public function getDetectionCriteria(): array
    {
        return [
            'files' => ['package.json'],
            'patterns' => self::NUXT_PACKAGES,
            'directories' => self::NUXT_DIRECTORIES,
        ];
    }

    /**
     * Create the Nuxt project structure document
     */
    private function createStructureDocument(array $projectMetadata): Document
    {
        return new Document(
            description: 'Nuxt Project Structure',
            outputPath: 'docs/nuxt-structure.md',
            overwrite: true,
            modifiers: [],
            tags: [],
            treeSource: new TreeSource(
                sourcePaths: ['pages', 'components', 'layouts', 'plugins'],
                description: 'Nuxt Directory Structure',
                treeView: new TreeViewConfig(
                    showCharCount: true,
                    includeFiles: true,
                    maxDepth: 3,
                ),
            ),
        );
    }
}
