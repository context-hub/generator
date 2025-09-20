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
 * Express.js project template definition
 */
final class ExpressTemplateDefinition implements TemplateDefinitionInterface
{
    /**
     * Express-specific directories that indicate an Express project
     */
    private const array EXPRESS_DIRECTORIES = [
        'routes',
        'middleware',
        'controllers',
        'models',
        'views',
        'public',
    ];

    /**
     * Express-specific packages in package.json
     */
    private const array EXPRESS_PACKAGES = [
        'express',
        'express-generator',
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
        return 'express';
    }

    public function getDescription(): string
    {
        return 'Express.js Node.js framework project template';
    }

    public function getTags(): array
    {
        return ['javascript', 'nodejs', 'express', 'backend', 'api'];
    }

    public function getPriority(): int
    {
        return 80; // Medium-high priority for backend framework
    }

    public function getDetectionCriteria(): array
    {
        return [
            'files' => ['package.json'],
            'patterns' => self::EXPRESS_PACKAGES,
            'directories' => self::EXPRESS_DIRECTORIES,
        ];
    }

    /**
     * Check if this definition can detect an Express project
     */
    public function canDetect(array $projectMetadata): bool
    {
        $packageJson = $projectMetadata['packageJson'] ?? null;

        if ($packageJson === null) {
            return false;
        }

        // Must have Express package
        return $this->hasPackage($packageJson, 'express');
    }

    /**
     * Create the Express application overview document
     */
    private function createOverviewDocument(array $projectMetadata): Document
    {
        return new Document(
            description: 'Express Application Overview',
            outputPath: 'docs/express-overview.md',
            overwrite: true,
            modifiers: [],
            tags: [],
            textSource: new TextSource(
                content: $this->generateOverviewContent($projectMetadata),
                description: 'Express Overview Header',
            ),
        );
    }

    /**
     * Create the Express project structure document
     */
    private function createStructureDocument(array $projectMetadata): Document
    {
        return new Document(
            description: 'Express Project Structure',
            outputPath: 'docs/express-structure.md',
            overwrite: true,
            modifiers: [],
            tags: [],
            treeSource: new TreeSource(
                sourcePaths: ['routes', 'controllers', 'middleware', 'models'],
                description: 'Express Directory Structure',
                treeView: new TreeViewConfig(
                    showCharCount: true,
                    includeFiles: true,
                    maxDepth: 3,
                ),
            ),
        );
    }

    /**
     * Generate overview content with Express-specific information
     */
    private function generateOverviewContent(array $projectMetadata): string
    {
        $packageJson = $projectMetadata['packageJson'] ?? null;
        $expressVersion = $this->getExpressVersion($packageJson);

        $content = <<<'CONTENT'
# Express Application Context

This document provides a comprehensive overview of the Express.js application structure and key components.

CONTENT;

        // Add Express version information if available
        if ($expressVersion !== null) {
            $content .= "**Express Version:** `{$expressVersion}`\n\n";
        }

        $content .= <<<'CONTENT'
## Project Structure

This Express application follows common Node.js backend patterns:

- **app.js** or **server.js**: Main application entry point
  - Express app configuration
  - Middleware setup
  - Route registration
  - Server startup

- **routes/**: Route definitions
  - **index.js**: Main routes
  - **users.js**: User-related routes
  - **api/**: API route modules
  - RESTful endpoint organization

- **controllers/**: Request handlers
  - Business logic separation
  - Request/response handling
  - Error management
  - Data validation

- **middleware/**: Custom middleware
  - Authentication middleware
  - Logging middleware
  - Error handling
  - Request processing

- **models/**: Data models
  - Database schemas
  - ORM/ODM models
  - Data validation
  - Business logic

- **views/**: Template files
  - EJS, Handlebars, Pug templates
  - Layout templates
  - Partial views

- **public/**: Static files
  - CSS stylesheets
  - JavaScript files
  - Images and assets
  - Client-side resources

- **config/**: Configuration files
  - Database configuration
  - Environment variables
  - Application settings

- **tests/**: Test files
  - Unit tests
  - Integration tests
  - API endpoint tests

## Key Express Concepts

### Middleware
Functions that execute during request-response cycle:
- **Application-level middleware**: App-wide functionality
- **Router-level middleware**: Route-specific processing
- **Error-handling middleware**: Error processing
- **Built-in middleware**: Express static, JSON parsing
- **Third-party middleware**: External middleware packages

### Routing
URL pattern matching and handling:
- **Basic routing**: GET, POST, PUT, DELETE
- **Route parameters**: Dynamic URL segments
- **Route handlers**: Multiple handler functions
- **Router module**: Modular route organization

### Request/Response
HTTP request and response handling:
- **Request object**: Headers, body, parameters
- **Response object**: Send data, set status codes
- **Response methods**: JSON, render, redirect
- **Content negotiation**: Accept headers

### Template Engines
Server-side rendering:
- **EJS**: Embedded JavaScript templates
- **Handlebars**: Logic-less templates
- **Pug**: Clean, whitespace-sensitive syntax
- **Mustache**: Logic-less templates

### Database Integration
Data persistence options:
- **MongoDB**: NoSQL with Mongoose ODM
- **PostgreSQL/MySQL**: SQL with Sequelize ORM
- **SQLite**: Lightweight SQL database
- **Redis**: In-memory data structure store

CONTENT;

        // Add package.json information if available
        if ($packageJson !== null) {
            $content .= "\n## Project Configuration\n\n";

            if (isset($packageJson['name'])) {
                $content .= "**Package Name:** `{$packageJson['name']}`\n\n";
            }

            if (isset($packageJson['description'])) {
                $content .= "**Description:** {$packageJson['description']}\n\n";
            }

            // Add scripts information
            $scripts = $packageJson['scripts'] ?? [];
            if (!empty($scripts)) {
                $content .= "**Available Scripts:**\n";
                foreach ($scripts as $script => $command) {
                    $content .= "- `npm run {$script}`: {$command}\n";
                }
                $content .= "\n";
            }
        }

        $content .= "\nGenerated automatically by CTX for Express.js projects.\n";

        return $content;
    }

    /**
     * Get Express version from package.json
     */
    private function getExpressVersion(?array $packageJson = null): ?string
    {
        if ($packageJson === null) {
            return null;
        }

        return $packageJson['dependencies']['express'] ?? $packageJson['devDependencies']['express'] ?? null;
    }

    /**
     * Check if a package is present in package.json dependencies
     */
    private function hasPackage(array $packageJson, string $packageName): bool
    {
        // Check in dependencies section
        if (isset($packageJson['dependencies']) && \array_key_exists($packageName, $packageJson['dependencies'])) {
            return true;
        }

        // Check in devDependencies section
        if (isset($packageJson['devDependencies']) && \array_key_exists($packageName, $packageJson['devDependencies'])) {
            return true;
        }

        return false;
    }
}
