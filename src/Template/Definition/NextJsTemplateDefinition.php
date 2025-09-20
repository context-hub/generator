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
 * Next.js project template definition
 */
final class NextJsTemplateDefinition implements TemplateDefinitionInterface
{
    /**
     * Next.js-specific directories that indicate a Next.js project
     */
    private const array NEXTJS_DIRECTORIES = [
        'pages',
        'app',
        'public',
        'components',
        'styles',
    ];

    /**
     * Next.js-specific packages in package.json
     */
    private const array NEXTJS_PACKAGES = [
        'next',
        'react',
        'react-dom',
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
        return 'nextjs';
    }

    public function getDescription(): string
    {
        return 'Next.js React framework project template';
    }

    public function getTags(): array
    {
        return ['javascript', 'react', 'nextjs', 'fullstack', 'ssr'];
    }

    public function getPriority(): int
    {
        return 88; // Higher priority than React, as Next.js is more specific
    }

    public function getDetectionCriteria(): array
    {
        return [
            'files' => ['package.json'],
            'patterns' => self::NEXTJS_PACKAGES,
            'directories' => self::NEXTJS_DIRECTORIES,
        ];
    }

    /**
     * Create the Next.js project structure document
     */
    private function createStructureDocument(array $projectMetadata): Document
    {
        return new Document(
            description: 'Next.js Project Structure',
            outputPath: 'docs/nextjs-structure.md',
            overwrite: true,
            modifiers: [],
            tags: [],
            treeSource: new TreeSource(
                sourcePaths: ['pages', 'app', 'public', 'components'],
                description: 'Next.js Directory Structure',
                treeView: new TreeViewConfig(
                    showCharCount: true,
                    includeFiles: true,
                    maxDepth: 3,
                ),
            ),
        );
    }
}
