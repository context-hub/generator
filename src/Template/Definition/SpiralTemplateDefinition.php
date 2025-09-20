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
     * Check if this definition can detect a Spiral project
     */
    public function canDetect(array $projectMetadata): bool
    {
        $composer = $projectMetadata['composer'] ?? null;

        if ($composer === null) {
            return false;
        }

        // Check for Spiral-specific packages
        foreach (self::SPIRAL_PACKAGES as $package) {
            if ($this->hasPackage($composer, $package)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create the Spiral application overview document
     */
    private function createOverviewDocument(array $projectMetadata): Document
    {
        return new Document(
            description: 'Spiral Application Overview',
            outputPath: 'docs/spiral-overview.md',
            overwrite: true,
            modifiers: [],
            tags: [],
            textSource: new TextSource(
                content: $this->generateOverviewContent($projectMetadata),
                description: 'Spiral Overview Header',
            ),
        );
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

    /**
     * Generate overview content with Spiral-specific information
     */
    private function generateOverviewContent(array $projectMetadata): string
    {
        $composer = $projectMetadata['composer'] ?? null;
        $spiralVersion = $this->getSpiralVersion($composer);

        $content = <<<'CONTENT'
# Spiral Framework Application Context

This document provides a comprehensive overview of the Spiral Framework application structure and key components.

CONTENT;

        // Add Spiral version information if available
        if ($spiralVersion !== null) {
            $content .= "**Spiral Framework Version:** `{$spiralVersion}`\n\n";
        }

        $content .= <<<'CONTENT'
## Project Structure

This Spiral application follows the standard Spiral Framework directory structure:

- **app/**: Contains the core application code
  - **src/**: Source code organized by domain
  - **config/**: Application configuration files
  - **Bootloader/**: Custom bootloaders for dependency injection

- **resources/**: Application resources
  - **views/**: Stempler/Twig templates
  - **i18n/**: Internationalization files

- **public/**: Web-accessible files
  - **index.php**: Application entry point
  - **assets/**: Static assets (CSS, JS, images)

- **runtime/**: Runtime files and cache
- **tests/**: Test files

## Key Spiral Concepts

### Bootloaders
Spiral uses bootloaders for dependency injection container configuration. They define how services are registered and configured.

### Interceptors
Spiral provides AOP (Aspect-Oriented Programming) through interceptors for cross-cutting concerns like logging, caching, and validation.

### Cycle ORM
Built-in ORM with advanced features like automatic migrations, relations, and schema introspection.

### RoadRunner
High-performance PHP application server that enables long-running processes and improved performance.

CONTENT;

        // Add composer information if available
        if ($composer !== null) {
            $content .= "\n## Dependencies\n\n";

            if (isset($composer['name'])) {
                $content .= "**Package Name:** `{$composer['name']}`\n\n";
            }

            if (isset($composer['description'])) {
                $content .= "**Description:** {$composer['description']}\n\n";
            }
        }

        $content .= "\nGenerated automatically by CTX for Spiral Framework projects.\n";

        return $content;
    }

    /**
     * Get Spiral Framework version from composer
     */
    private function getSpiralVersion(?array $composer = null): ?string
    {
        if ($composer === null) {
            return null;
        }

        foreach (self::SPIRAL_PACKAGES as $package) {
            if (isset($composer['require'][$package])) {
                return $composer['require'][$package];
            }
        }

        return null;
    }

    /**
     * Check if a package is present in composer dependencies
     */
    private function hasPackage(array $composer, string $packageName): bool
    {
        // Check in require section
        if (isset($composer['require']) && \array_key_exists($packageName, $composer['require'])) {
            return true;
        }

        // Check in require-dev section
        if (isset($composer['require-dev']) && \array_key_exists($packageName, $composer['require-dev'])) {
            return true;
        }

        return false;
    }
}
