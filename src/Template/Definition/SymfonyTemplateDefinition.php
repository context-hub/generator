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
 * Symfony PHP Framework project template definition
 */
final class SymfonyTemplateDefinition implements TemplateDefinitionInterface
{
    /**
     * Symfony-specific directories that indicate a Symfony project
     */
    private const array SYMFONY_DIRECTORIES = [
        'src',
        'config',
        'templates',
        'public',
    ];

    /**
     * Symfony-specific files that indicate a Symfony project
     */
    private const array SYMFONY_FILES = [
        'bin/console',
        'symfony.lock',
    ];

    /**
     * Symfony-specific packages in composer.json
     */
    private const array SYMFONY_PACKAGES = [
        'symfony/framework-bundle',
        'symfony/console',
        'symfony/dotenv',
        'symfony/flex',
        'symfony/runtime',
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
        return 'symfony';
    }

    public function getDescription(): string
    {
        return 'Symfony PHP Framework project template';
    }

    public function getTags(): array
    {
        return ['php', 'symfony', 'framework', 'web'];
    }

    public function getPriority(): int
    {
        return 95; // High priority for specific framework detection
    }

    public function getDetectionCriteria(): array
    {
        return [
            'files' => \array_merge(['composer.json'], self::SYMFONY_FILES),
            'patterns' => self::SYMFONY_PACKAGES,
            'directories' => self::SYMFONY_DIRECTORIES,
        ];
    }

    /**
     * Create the Symfony project structure document
     */
    private function createStructureDocument(array $projectMetadata): Document
    {
        return new Document(
            description: 'Symfony Project Structure',
            outputPath: 'docs/symfony-structure.md',
            overwrite: true,
            modifiers: [],
            tags: [],
            treeSource: new TreeSource(
                sourcePaths: ['src', 'config', 'templates', 'public'],
                description: 'Symfony Directory Structure',
                treeView: new TreeViewConfig(
                    showCharCount: true,
                    includeFiles: true,
                    maxDepth: 3,
                ),
            ),
        );
    }
}
