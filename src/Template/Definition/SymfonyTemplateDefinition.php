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
     * Check if this definition can detect a Symfony project
     */
    public function canDetect(array $projectMetadata): bool
    {
        $composer = $projectMetadata['composer'] ?? null;

        if ($composer === null) {
            return false;
        }

        // Check for Symfony-specific packages
        foreach (self::SYMFONY_PACKAGES as $package) {
            if ($this->hasPackage($composer, $package)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create the Symfony application overview document
     */
    private function createOverviewDocument(array $projectMetadata): Document
    {
        return new Document(
            description: 'Symfony Application Overview',
            outputPath: 'docs/symfony-overview.md',
            overwrite: true,
            modifiers: [],
            tags: [],
            textSource: new TextSource(
                content: $this->generateOverviewContent($projectMetadata),
                description: 'Symfony Overview Header',
            ),
        );
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

    /**
     * Generate overview content with Symfony-specific information
     */
    private function generateOverviewContent(array $projectMetadata): string
    {
        $composer = $projectMetadata['composer'] ?? null;
        $symfonyVersion = $this->getSymfonyVersion($composer);

        $content = <<<'CONTENT'
# Symfony Framework Application Context

This document provides a comprehensive overview of the Symfony Framework application structure and key components.

CONTENT;

        // Add Symfony version information if available
        if ($symfonyVersion !== null) {
            $content .= "**Symfony Version:** `{$symfonyVersion}`\n\n";
        }

        $content .= <<<'CONTENT'
## Project Structure

This Symfony application follows the standard Symfony directory structure:

- **src/**: Contains the core application code
  - **Controller/**: Request handlers and route actions
  - **Entity/**: Doctrine ORM entities
  - **Repository/**: Custom repository classes
  - **Service/**: Business logic services
  - **EventSubscriber/**: Event subscribers
  - **Command/**: Console commands

- **config/**: Configuration files
  - **packages/**: Bundle-specific configuration
  - **routes/**: Routing configuration
  - **services.yaml**: Service container configuration

- **templates/**: Twig templates
  - Organized by controller or feature
  - **base.html.twig**: Base template

- **public/**: Web-accessible files
  - **index.php**: Front controller
  - **assets/**: Static assets

- **var/**: Runtime files
  - **cache/**: Application cache
  - **log/**: Application logs

- **migrations/**: Database migrations
- **tests/**: Test files

## Key Symfony Concepts

### Dependency Injection Container
Symfony's powerful DI container manages service instantiation and configuration through autowiring and explicit service definitions.

### Event Dispatcher
Symfony uses an event-driven architecture where components can dispatch and listen to events for loose coupling.

### Doctrine ORM
Integrated Object-Relational Mapping with entities, repositories, and database migrations.

### Twig Templating
Powerful templating engine with inheritance, macros, and security features.

### Console Component
Command-line interface for maintenance tasks, custom commands, and utilities.

### Security Component
Comprehensive authentication and authorization system with voters, guards, and firewalls.

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

        $content .= "\nGenerated automatically by CTX for Symfony Framework projects.\n";

        return $content;
    }

    /**
     * Get Symfony Framework version from composer
     */
    private function getSymfonyVersion(?array $composer = null): ?string
    {
        if ($composer === null) {
            return null;
        }

        foreach (self::SYMFONY_PACKAGES as $package) {
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
