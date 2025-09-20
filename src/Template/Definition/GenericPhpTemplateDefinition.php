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
 * Generic PHP project template definition
 */
final class GenericPhpTemplateDefinition implements TemplateDefinitionInterface
{
    /**
     * Common PHP source directories to detect and include
     */
    private const array PHP_SOURCE_DIRECTORIES = [
        'src',
        'app',
        'lib',
        'classes',
        'includes',
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
        return 'generic-php';
    }

    public function getDescription(): string
    {
        return 'Generic PHP project template';
    }

    public function getTags(): array
    {
        return ['php', 'generic'];
    }

    public function getPriority(): int
    {
        return 10;
    }

    public function getDetectionCriteria(): array
    {
        return [
            'files' => ['composer.json'],
            'directories' => self::PHP_SOURCE_DIRECTORIES,
        ];
    }

    /**
     * Create the PHP project structure document
     */
    private function createStructureDocument(array $projectMetadata): Document
    {
        $sourcePaths = $this->getDetectedSourcePaths($projectMetadata);

        return new Document(
            description: 'PHP Project Structure',
            outputPath: 'docs/php-structure.md',
            overwrite: true,
            modifiers: [],
            tags: [],
            treeSource: new TreeSource(
                sourcePaths: $sourcePaths,
                description: 'PHP Directory Structure',
                treeView: new TreeViewConfig(
                    showCharCount: true,
                    includeFiles: true,
                    maxDepth: 3,
                ),
            ),
        );
    }

    /**
     * Get detected source paths from project metadata
     */
    private function getDetectedSourcePaths(array $projectMetadata): array
    {
        $existingDirs = $projectMetadata['existingDirectories'] ?? [];

        // Filter to only include common PHP source directories that exist
        $sourcePaths = \array_intersect($existingDirs, self::PHP_SOURCE_DIRECTORIES);

        // If no standard source directories found, fall back to 'src'
        if (empty($sourcePaths)) {
            return ['src'];
        }

        return \array_values($sourcePaths);
    }
}
