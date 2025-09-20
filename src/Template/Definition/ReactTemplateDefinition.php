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
 * React.js project template definition
 */
final class ReactTemplateDefinition implements TemplateDefinitionInterface
{
    /**
     * React-specific directories that indicate a React project
     */
    private const array REACT_DIRECTORIES = [
        'src',
        'public',
        'components',
        'pages',
    ];

    /**
     * React-specific packages in package.json
     */
    private const array REACT_PACKAGES = [
        'react',
        'react-dom',
        'react-scripts',
        '@types/react',
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
        return 'react';
    }

    public function getDescription(): string
    {
        return 'React.js application project template';
    }

    public function getTags(): array
    {
        return ['javascript', 'react', 'frontend', 'spa'];
    }

    public function getPriority(): int
    {
        return 85; // High priority for specific framework detection
    }

    public function getDetectionCriteria(): array
    {
        return [
            'files' => ['package.json'],
            'patterns' => self::REACT_PACKAGES,
            'directories' => self::REACT_DIRECTORIES,
        ];
    }

    /**
     * Create the React project structure document
     */
    private function createStructureDocument(array $projectMetadata): Document
    {
        return new Document(
            description: 'React Project Structure',
            outputPath: 'docs/react-structure.md',
            overwrite: true,
            modifiers: [],
            tags: [],
            treeSource: new TreeSource(
                sourcePaths: ['src', 'public'],
                description: 'React Directory Structure',
                treeView: new TreeViewConfig(
                    showCharCount: true,
                    includeFiles: true,
                    maxDepth: 3,
                ),
            ),
        );
    }
}
