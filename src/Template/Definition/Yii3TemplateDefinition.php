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
 * Yii3 PHP Framework project template definition
 */
final class Yii3TemplateDefinition implements TemplateDefinitionInterface
{
    /**
     * Yii3-specific directories that indicate a Yii3 project
     */
    private const array YII3_DIRECTORIES = [
        'src',
        'config',
        'resources',
        'public',
    ];

    /**
     * Yii3-specific files that indicate a Yii3 project
     */
    private const array YII3_FILES = [
        'yii',
    ];

    /**
     * Yii3-specific packages in composer.json
     */
    private const array YII3_PACKAGES = [
        'yiisoft/yii-web',
        'yiisoft/yii-console',
        'yiisoft/yii-runner-http',
        'yiisoft/yii-runner-console',
        'yiisoft/di',
        'yiisoft/config',
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
        return 'yii3';
    }

    public function getDescription(): string
    {
        return 'Yii3 PHP Framework project template';
    }

    public function getTags(): array
    {
        return ['php', 'yii3', 'framework', 'web', 'modern'];
    }

    public function getPriority(): int
    {
        return 92; // High priority for specific framework detection, slightly higher than Yii2
    }

    public function getDetectionCriteria(): array
    {
        return [
            'files' => \array_merge(['composer.json'], self::YII3_FILES),
            'patterns' => self::YII3_PACKAGES,
            'directories' => self::YII3_DIRECTORIES,
        ];
    }

    /**
     * Check if this definition can detect a Yii3 project
     */
    public function canDetect(array $projectMetadata): bool
    {
        $composer = $projectMetadata['composer'] ?? null;

        if ($composer === null) {
            return false;
        }

        // Check for Yii3-specific packages
        foreach (self::YII3_PACKAGES as $package) {
            if ($this->hasPackage($composer, $package)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create the Yii3 application overview document
     */
    private function createOverviewDocument(array $projectMetadata): Document
    {
        return new Document(
            description: 'Yii3 Application Overview',
            outputPath: 'docs/yii3-overview.md',
            overwrite: true,
            modifiers: [],
            tags: [],
            textSource: new TextSource(
                content: $this->generateOverviewContent($projectMetadata),
                description: 'Yii3 Overview Header',
            ),
        );
    }

    /**
     * Create the Yii3 project structure document
     */
    private function createStructureDocument(array $projectMetadata): Document
    {
        return new Document(
            description: 'Yii3 Project Structure',
            outputPath: 'docs/yii3-structure.md',
            overwrite: true,
            modifiers: [],
            tags: [],
            treeSource: new TreeSource(
                sourcePaths: ['src', 'config', 'resources', 'public'],
                description: 'Yii3 Directory Structure',
                treeView: new TreeViewConfig(
                    showCharCount: true,
                    includeFiles: true,
                    maxDepth: 3,
                ),
            ),
        );
    }

    /**
     * Generate overview content with Yii3-specific information
     */
    private function generateOverviewContent(array $projectMetadata): string
    {
        $composer = $projectMetadata['composer'] ?? null;
        $yiiPackages = $this->getYiiPackages($composer);

        $content = <<<'CONTENT'
# Yii3 Framework Application Context

This document provides a comprehensive overview of the Yii3 Framework application structure and key components.

CONTENT;

        // Add Yii3 packages information if available
        if (!empty($yiiPackages)) {
            $content .= "**Yii3 Packages:**\n";
            foreach ($yiiPackages as $package => $version) {
                $content .= "- `{$package}`: `{$version}`\n";
            }
            $content .= "\n";
        }

        $content .= <<<'CONTENT'
## Project Structure

This Yii3 application follows the modern Yii3 directory structure:

- **src/**: Contains the core application code
  - **Controller/**: HTTP request handlers
  - **Service/**: Business logic services
  - **Repository/**: Data access layer
  - **Entity/**: Domain entities
  - **Middleware/**: HTTP middleware components
  - **Command/**: Console commands

- **config/**: Configuration files
  - **common/**: Shared configuration
  - **web/**: Web-specific configuration
  - **console/**: Console-specific configuration
  - **params.php**: Application parameters

- **resources/**: Application resources
  - **views/**: View templates
  - **assets/**: Frontend assets
  - **translations/**: Internationalization files

- **public/**: Web-accessible files
  - **index.php**: Web application entry point
  - **assets/**: Compiled assets

- **runtime/**: Runtime files
  - **cache/**: Application cache
  - **logs/**: Application logs

- **tests/**: Test files organized by type

## Key Yii3 Concepts

### Modular Architecture
Yii3 is built with a modular approach using separate packages:
- Each package serves a specific purpose
- Packages can be used independently
- Flexible composition of functionality

### Dependency Injection
Modern DI container with:
- Constructor injection
- Interface-based configuration
- Lazy loading support
- Service providers

### PSR Compliance
Full PSR (PHP Standard Recommendation) compliance:
- PSR-3: Logger Interface
- PSR-4: Autoloader
- PSR-7: HTTP Message Interface
- PSR-11: Container Interface
- PSR-15: HTTP Server Request Handlers
- PSR-17: HTTP Factories

### Configuration System
Flexible configuration with:
- Environment-based configuration
- Mergeable configuration files
- Parameter substitution
- Configuration validation

### Middleware Stack
HTTP middleware pattern for:
- Request/response processing
- Authentication and authorization
- CORS handling
- Rate limiting

### Database Abstraction
Modern database layer with:
- Query builder
- Active Record pattern
- Migration system
- Connection pooling

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

        $content .= "\nGenerated automatically by CTX for Yii3 Framework projects.\n";

        return $content;
    }

    /**
     * Get Yii3 packages from composer
     */
    private function getYiiPackages(?array $composer = null): array
    {
        if ($composer === null) {
            return [];
        }

        $packages = [];
        $allDeps = \array_merge(
            $composer['require'] ?? [],
            $composer['require-dev'] ?? [],
        );

        foreach ($allDeps as $package => $version) {
            if (\str_starts_with((string) $package, 'yiisoft/')) {
                $packages[$package] = $version;
            }
        }

        return $packages;
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
