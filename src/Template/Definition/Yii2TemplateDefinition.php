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
 * Yii2 PHP Framework project template definition
 */
final class Yii2TemplateDefinition implements TemplateDefinitionInterface
{
    /**
     * Yii2-specific directories that indicate a Yii2 project
     */
    private const array YII2_DIRECTORIES = [
        'controllers',
        'models',
        'views',
        'web',
        'config',
    ];

    /**
     * Yii2-specific files that indicate a Yii2 project
     */
    private const array YII2_FILES = [
        'yii',
        'requirements.php',
    ];

    /**
     * Yii2-specific packages in composer.json
     */
    private const array YII2_PACKAGES = [
        'yiisoft/yii2',
        'yiisoft/yii2-app-basic',
        'yiisoft/yii2-app-advanced',
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
        return 'yii2';
    }

    public function getDescription(): string
    {
        return 'Yii2 PHP Framework project template';
    }

    public function getTags(): array
    {
        return ['php', 'yii2', 'framework', 'web'];
    }

    public function getPriority(): int
    {
        return 90; // High priority for specific framework detection
    }

    public function getDetectionCriteria(): array
    {
        return [
            'files' => \array_merge(['composer.json'], self::YII2_FILES),
            'patterns' => self::YII2_PACKAGES,
            'directories' => self::YII2_DIRECTORIES,
        ];
    }

    /**
     * Check if this definition can detect a Yii2 project
     */
    public function canDetect(array $projectMetadata): bool
    {
        $composer = $projectMetadata['composer'] ?? null;

        if ($composer === null) {
            return false;
        }

        // Check for Yii2-specific packages
        foreach (self::YII2_PACKAGES as $package) {
            if ($this->hasPackage($composer, $package)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create the Yii2 application overview document
     */
    private function createOverviewDocument(array $projectMetadata): Document
    {
        return new Document(
            description: 'Yii2 Application Overview',
            outputPath: 'docs/yii2-overview.md',
            overwrite: true,
            modifiers: [],
            tags: [],
            textSource: new TextSource(
                content: $this->generateOverviewContent($projectMetadata),
                description: 'Yii2 Overview Header',
            ),
        );
    }

    /**
     * Create the Yii2 project structure document
     */
    private function createStructureDocument(array $projectMetadata): Document
    {
        return new Document(
            description: 'Yii2 Project Structure',
            outputPath: 'docs/yii2-structure.md',
            overwrite: true,
            modifiers: [],
            tags: [],
            treeSource: new TreeSource(
                sourcePaths: ['controllers', 'models', 'views', 'web', 'config'],
                description: 'Yii2 Directory Structure',
                treeView: new TreeViewConfig(
                    showCharCount: true,
                    includeFiles: true,
                    maxDepth: 3,
                ),
            ),
        );
    }

    /**
     * Generate overview content with Yii2-specific information
     */
    private function generateOverviewContent(array $projectMetadata): string
    {
        $composer = $projectMetadata['composer'] ?? null;
        $yiiVersion = $this->getYiiVersion($composer);

        $content = <<<'CONTENT'
# Yii2 Framework Application Context

This document provides a comprehensive overview of the Yii2 Framework application structure and key components.

CONTENT;

        // Add Yii2 version information if available
        if ($yiiVersion !== null) {
            $content .= "**Yii2 Version:** `{$yiiVersion}`\n\n";
        }

        $content .= <<<'CONTENT'
## Project Structure

This Yii2 application follows the standard Yii2 directory structure:

- **controllers/**: Request handlers and actions
  - **SiteController.php**: Default site controller
  - Custom controller classes extending Controller

- **models/**: Data models and business logic
  - **ActiveRecord** classes for database entities
  - **Form** models for input validation
  - **Search** models for filtering data

- **views/**: View templates
  - Organized by controller name
  - **layouts/**: Common layout templates
  - **site/**: Views for SiteController

- **web/**: Web-accessible files
  - **index.php**: Application entry point
  - **assets/**: CSS, JS, and image files
  - **.htaccess**: URL rewriting rules

- **config/**: Configuration files
  - **web.php**: Web application configuration
  - **console.php**: Console application configuration
  - **db.php**: Database configuration
  - **params.php**: Application parameters

- **runtime/**: Runtime files
  - **cache/**: Application cache
  - **logs/**: Application logs

- **vendor/**: Composer dependencies
- **tests/**: Test files

## Key Yii2 Concepts

### MVC Pattern
Yii2 implements the Model-View-Controller pattern:
- **Models**: Handle data and business logic
- **Views**: Handle presentation layer
- **Controllers**: Handle user input and coordinate between models and views

### ActiveRecord
Object-Relational Mapping with database abstraction:
- Database table representation as PHP classes
- Automatic validation and data sanitization
- Relations between models

### Gii Code Generator
Web-based code generator for:
- Models from database tables
- CRUD controllers and views
- Extensions and modules

### Widgets
Reusable UI components:
- GridView for data tables
- DetailView for record details
- ActiveForm for forms

### Asset Management
Organized CSS and JavaScript dependency management with minification and compression.

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

        $content .= "\nGenerated automatically by CTX for Yii2 Framework projects.\n";

        return $content;
    }

    /**
     * Get Yii2 Framework version from composer
     */
    private function getYiiVersion(?array $composer = null): ?string
    {
        if ($composer === null) {
            return null;
        }

        foreach (self::YII2_PACKAGES as $package) {
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
