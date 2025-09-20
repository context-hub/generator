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
 * Spiral PHP Framework project template definition
 */
final class SpiralTemplateDefinition implements TemplateDefinitionInterface
{
    /**
     * Spiral-specific directories that indicate a Spiral project
     */
    private const array SPIRAL_DIRECTORIES = [
        'app',
        'resources',
        'public',
    ];

    /**
     * Spiral-specific packages in composer.json
     */
    private const array SPIRAL_PACKAGES = [
        'spiral/framework',
        'spiral/roadrunner-cli',
        'spiral/nyholm-bridge',
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
        return 'spiral';
    }

    public function getDescription(): string
    {
        return 'Spiral PHP Framework project template';
    }

    public function getTags(): array
    {
        return ['php', 'spiral', 'framework', 'roadrunner'];
    }

    public function getPriority(): int
    {
        return 95; // High priority for specific framework detection
    }

    public function getDetectionCriteria(): array
    {
        return [
            'files' => ['composer.json'],
            'patterns' => self::SPIRAL_PACKAGES,
            'directories' => self::SPIRAL_DIRECTORIES,
        ];
    }

    /**
     * Create the Spiral project structure document
     */
    private function createStructureDocument(array $projectMetadata): Document
    {
        return new Document(
            description: 'Spiral Project Structure',
            outputPath: 'docs/spiral-structure.md',
            overwrite: true,
            modifiers: [],
            tags: [],
            treeSource: new TreeSource(
                sourcePaths: ['app', 'resources', 'public', 'config'],
                description: 'Spiral Directory Structure',
                treeView: new TreeViewConfig(
                    showCharCount: true,
                    includeFiles: true,
                    maxDepth: 3,
                ),
            ),
        );
    }
}
