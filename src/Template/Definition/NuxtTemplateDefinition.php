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
 * Nuxt.js project template definition
 */
final class NuxtTemplateDefinition implements TemplateDefinitionInterface
{
    /**
     * Nuxt-specific directories that indicate a Nuxt project
     */
    private const array NUXT_DIRECTORIES = [
        'pages',
        'components',
        'layouts',
        'plugins',
        'middleware',
        'assets',
        'static',
        'public',
    ];

    /**
     * Nuxt-specific packages in package.json
     */
    private const array NUXT_PACKAGES = [
        'nuxt',
        '@nuxt/kit',
        'nuxt3',
        '@nuxt/cli',
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
        return 'nuxt';
    }

    public function getDescription(): string
    {
        return 'Nuxt.js Vue framework project template';
    }

    public function getTags(): array
    {
        return ['javascript', 'vue', 'nuxt', 'fullstack', 'ssr'];
    }

    public function getPriority(): int
    {
        return 88; // Higher priority than Vue, as Nuxt is more specific
    }

    public function getDetectionCriteria(): array
    {
        return [
            'files' => ['package.json'],
            'patterns' => self::NUXT_PACKAGES,
            'directories' => self::NUXT_DIRECTORIES,
        ];
    }

    /**
     * Check if this definition can detect a Nuxt project
     */
    public function canDetect(array $projectMetadata): bool
    {
        $packageJson = $projectMetadata['packageJson'] ?? null;

        if ($packageJson === null) {
            return false;
        }

        // Check for Nuxt-specific packages
        foreach (self::NUXT_PACKAGES as $package) {
            if ($this->hasPackage($packageJson, $package)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create the Nuxt application overview document
     */
    private function createOverviewDocument(array $projectMetadata): Document
    {
        return new Document(
            description: 'Nuxt Application Overview',
            outputPath: 'docs/nuxt-overview.md',
            overwrite: true,
            modifiers: [],
            tags: [],
            textSource: new TextSource(
                content: $this->generateOverviewContent($projectMetadata),
                description: 'Nuxt Overview Header',
            ),
        );
    }

    /**
     * Create the Nuxt project structure document
     */
    private function createStructureDocument(array $projectMetadata): Document
    {
        return new Document(
            description: 'Nuxt Project Structure',
            outputPath: 'docs/nuxt-structure.md',
            overwrite: true,
            modifiers: [],
            tags: [],
            treeSource: new TreeSource(
                sourcePaths: ['pages', 'components', 'layouts', 'plugins'],
                description: 'Nuxt Directory Structure',
                treeView: new TreeViewConfig(
                    showCharCount: true,
                    includeFiles: true,
                    maxDepth: 3,
                ),
            ),
        );
    }

    /**
     * Generate overview content with Nuxt-specific information
     */
    private function generateOverviewContent(array $projectMetadata): string
    {
        $packageJson = $projectMetadata['packageJson'] ?? null;
        $nuxtVersion = $this->getNuxtVersion($packageJson);

        $content = <<<'CONTENT'
# Nuxt Application Context

This document provides a comprehensive overview of the Nuxt application structure and key features.

CONTENT;

        // Add Nuxt version information if available
        if ($nuxtVersion !== null) {
            $content .= "**Nuxt Version:** `{$nuxtVersion}`\n\n";
        }

        $content .= <<<'CONTENT'
## Project Structure

This Nuxt application follows the convention-over-configuration approach:

- **pages/**: File-based routing
  - Automatic route generation
  - Dynamic routes with parameters
  - Nested routes and layouts

- **components/**: Vue components
  - Auto-imported components
  - Global and scoped components
  - Component discovery

- **layouts/**: Page layouts
  - **default.vue**: Default layout
  - Custom layouts for specific pages
  - Error layouts

- **plugins/**: JavaScript plugins
  - Client-side and server-side plugins
  - Auto-registration
  - Vue.js plugins integration

- **middleware/**: Route middleware
  - Authentication middleware
  - Route guards
  - Global and page-specific middleware

- **assets/**: Build assets
  - Processed by build tools
  - SCSS/CSS files
  - Images and fonts

- **static/** or **public/**: Static files
  - Served directly
  - Favicon, robots.txt
  - Static images and assets

- **store/**: Vuex store (Nuxt 2) or Pinia (Nuxt 3)
  - State management
  - Actions and mutations
  - Auto-registration

- **server/**: Server-side code
  - API routes
  - Server middleware
  - Database connections

## Key Nuxt Features

### Universal Application
Multiple rendering modes:
- **Universal (SSR)**: Server-side rendering
- **Static (SSG)**: Static site generation
- **SPA**: Single page application
- **Hybrid**: Mix of SSR and SSG

### Auto-imports
Automatic imports for:
- Vue 3 Composition API
- Components
- Composables
- Utilities
- Third-party libraries

### File-based Routing
Convention-based routing:
- **pages/index.vue** → `/`
- **pages/users/index.vue** → `/users`
- **pages/users/[id].vue** → `/users/:id`
- **pages/users/[...slug].vue** → `/users/*`

### Data Fetching
Multiple data fetching strategies:
- **useFetch**: Reactive data fetching
- **$fetch**: Fetch utility
- **asyncData**: Page-level data fetching (Nuxt 2)
- **fetch**: Component-level data fetching (Nuxt 2)

### SEO and Meta
Built-in SEO optimization:
- **useHead**: Reactive head management
- **useSeoMeta**: SEO meta tags
- **Social media tags**: Open Graph, Twitter
- **Structured data**: JSON-LD support

### CSS and Styling
Multiple styling options:
- **CSS Modules**: Scoped CSS
- **SCSS/SASS**: CSS preprocessing
- **PostCSS**: CSS transformations
- **Tailwind CSS**: Utility-first CSS
- **CSS-in-JS**: Runtime styling

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

        $content .= "\nGenerated automatically by CTX for Nuxt projects.\n";

        return $content;
    }

    /**
     * Get Nuxt version from package.json
     */
    private function getNuxtVersion(?array $packageJson = null): ?string
    {
        if ($packageJson === null) {
            return null;
        }

        foreach (self::NUXT_PACKAGES as $package) {
            if (isset($packageJson['dependencies'][$package])) {
                return $packageJson['dependencies'][$package];
            }
            if (isset($packageJson['devDependencies'][$package])) {
                return $packageJson['devDependencies'][$package];
            }
        }

        return null;
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
