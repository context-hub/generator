<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Definition;

use Butschster\ContextGenerator\Config\Registry\ConfigRegistry;
use Butschster\ContextGenerator\Document\Document;
use Butschster\ContextGenerator\Document\DocumentRegistry;
use Butschster\ContextGenerator\Lib\TreeBuilder\TreeViewConfig;
use Butschster\ContextGenerator\Source\Tree\TreeSource;
use Butschster\ContextGenerator\Source\Text\TextSource;
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

    #[\Override]
    public function createTemplate(array $projectMetadata = []): Template
    {
        $config = new ConfigRegistry();

        $documents = new DocumentRegistry([
            $this->createOverviewDocument($projectMetadata),
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

    #[\Override]
    public function getName(): string
    {
        return 'generic-php';
    }

    #[\Override]
    public function getDescription(): string
    {
        return 'Generic PHP project template';
    }

    #[\Override]
    public function getTags(): array
    {
        return ['php', 'generic'];
    }

    #[\Override]
    public function getPriority(): int
    {
        return 10;
    }

    #[\Override]
    public function getDetectionCriteria(): array
    {
        return [
            'files' => ['composer.json'],
            'directories' => self::PHP_SOURCE_DIRECTORIES,
        ];
    }

    /**
     * Create the PHP project overview document
     */
    private function createOverviewDocument(array $projectMetadata): Document
    {
        return new Document(
            description: 'PHP Project Overview',
            outputPath: 'docs/php-overview.md',
            overwrite: true,
            modifiers: [],
            tags: [],
            textSource: new TextSource(
                content: $this->generateOverviewContent($projectMetadata),
                description: 'PHP Overview Header',
            ),
        );
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
     * Generate overview content based on detected project structure
     */
    private function generateOverviewContent(array $projectMetadata): string
    {
        $existingDirs = $projectMetadata['existingDirectories'] ?? [];
        $composer = $projectMetadata['composer'] ?? null;

        $content = <<<'CONTENT'
# PHP Project Context

This document provides an overview of the PHP project structure and components.

## Project Structure

CONTENT;

        // Add project structure details based on detected directories
        if (!empty($existingDirs)) {
            $content .= "\nThis PHP project contains the following directories:\n\n";

            foreach ($existingDirs as $dir) {
                $content .= $this->getDirectoryDescription($dir);
            }
        }

        // Add composer information if available
        if ($composer !== null) {
            $content .= "\n## Dependencies\n\n";

            if (isset($composer['name'])) {
                $content .= "**Package Name:** `{$composer['name']}`\n\n";
            }

            if (isset($composer['description'])) {
                $content .= "**Description:** {$composer['description']}\n\n";
            }

            $requireCount = isset($composer['require']) ? \count($composer['require']) : 0;
            $devRequireCount = isset($composer['require-dev']) ? \count($composer['require-dev']) : 0;

            if ($requireCount > 0 || $devRequireCount > 0) {
                $content .= "This project has:\n";
                if ($requireCount > 0) {
                    $content .= "- **{$requireCount}** production dependencies\n";
                }
                if ($devRequireCount > 0) {
                    $content .= "- **{$devRequireCount}** development dependencies\n";
                }
                $content .= "\n";
            }
        }

        $content .= <<<'CONTENT'

## Development Guidelines

- Follow PSR-12 coding standards
- Use Composer for dependency management
- Write comprehensive tests
- Document public APIs

Generated automatically by CTX for PHP projects.
CONTENT;

        return $content;
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

    /**
     * Get description for a directory
     */
    private function getDirectoryDescription(string $dir): string
    {
        return match ($dir) {
            'src' => "- **src/**: Main source code directory\n  - Contains the primary application logic\n  - Organized by namespace structure\n  - Follow PSR-4 autoloading standards\n\n",
            'app' => "- **app/**: Application source code\n  - Contains core application logic\n  - May include models, controllers, services\n\n",
            'lib' => "- **lib/**: Library code\n  - Contains reusable library components\n  - Often third-party or shared code\n\n",
            'classes' => "- **classes/**: Class files\n  - Contains PHP class definitions\n  - Legacy or custom class organization\n\n",
            'includes' => "- **includes/**: Include files\n  - Contains files to be included/required\n  - Configuration, utilities, or legacy code\n\n",
            'tests' => "- **tests/**: Test files\n  - Unit tests and integration tests\n  - Follow same structure as source code\n\n",
            'config' => "- **config/**: Configuration files\n  - Application configuration\n  - Environment-specific settings\n\n",
            'resources' => "- **resources/**: Resource files\n  - Assets, templates, language files\n\n",
            'templates' => "- **templates/**: Template files\n  - View templates and layouts\n\n",
            'views' => "- **views/**: View files\n  - Presentation layer templates\n\n",
            default => "- **{$dir}/**: Directory containing project files\n\n",
        };
    }
}
