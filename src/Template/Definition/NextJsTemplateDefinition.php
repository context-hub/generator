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
 * Next.js project template definition
 */
final class NextJsTemplateDefinition implements TemplateDefinitionInterface
{
    /**
     * Next.js-specific directories that indicate a Next.js project
     */
    private const array NEXTJS_DIRECTORIES = [
        'pages',
        'app',
        'public',
        'components',
        'styles',
    ];

    /**
     * Next.js-specific packages in package.json
     */
    private const array NEXTJS_PACKAGES = [
        'next',
        'react',
        'react-dom',
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
        return 'nextjs';
    }

    public function getDescription(): string
    {
        return 'Next.js React framework project template';
    }

    public function getTags(): array
    {
        return ['javascript', 'react', 'nextjs', 'fullstack', 'ssr'];
    }

    public function getPriority(): int
    {
        return 88; // Higher priority than React, as Next.js is more specific
    }

    public function getDetectionCriteria(): array
    {
        return [
            'files' => ['package.json'],
            'patterns' => self::NEXTJS_PACKAGES,
            'directories' => self::NEXTJS_DIRECTORIES,
        ];
    }

    /**
     * Check if this definition can detect a Next.js project
     */
    public function canDetect(array $projectMetadata): bool
    {
        $packageJson = $projectMetadata['packageJson'] ?? null;

        if ($packageJson === null) {
            return false;
        }

        // Must have Next.js package
        return $this->hasPackage($packageJson, 'next');
    }

    /**
     * Create the Next.js application overview document
     */
    private function createOverviewDocument(array $projectMetadata): Document
    {
        return new Document(
            description: 'Next.js Application Overview',
            outputPath: 'docs/nextjs-overview.md',
            overwrite: true,
            modifiers: [],
            tags: [],
            textSource: new TextSource(
                content: $this->generateOverviewContent($projectMetadata),
                description: 'Next.js Overview Header',
            ),
        );
    }

    /**
     * Create the Next.js project structure document
     */
    private function createStructureDocument(array $projectMetadata): Document
    {
        return new Document(
            description: 'Next.js Project Structure',
            outputPath: 'docs/nextjs-structure.md',
            overwrite: true,
            modifiers: [],
            tags: [],
            treeSource: new TreeSource(
                sourcePaths: ['pages', 'app', 'public', 'components'],
                description: 'Next.js Directory Structure',
                treeView: new TreeViewConfig(
                    showCharCount: true,
                    includeFiles: true,
                    maxDepth: 3,
                ),
            ),
        );
    }

    /**
     * Generate overview content with Next.js-specific information
     */
    private function generateOverviewContent(array $projectMetadata): string
    {
        $packageJson = $projectMetadata['packageJson'] ?? null;
        $nextVersion = $this->getNextVersion($packageJson);
        $isAppRouter = $this->detectAppRouter($projectMetadata);

        $content = <<<'CONTENT'
# Next.js Application Context

This document provides a comprehensive overview of the Next.js application structure and key features.

CONTENT;

        // Add Next.js version information if available
        if ($nextVersion !== null) {
            $content .= "**Next.js Version:** `{$nextVersion}`\n\n";
        }

        // Add router information
        if ($isAppRouter) {
            $content .= "**Router:** App Router (Next.js 13+)\n\n";
        } else {
            $content .= "**Router:** Pages Router (Legacy)\n\n";
        }

        $content .= <<<'CONTENT'
## Project Structure

This Next.js application uses modern full-stack development patterns:

### App Router Structure (Next.js 13+)
- **app/**: App Router directory (if using App Router)
  - **layout.js**: Root layout component
  - **page.js**: Page components
  - **loading.js**: Loading UI components
  - **error.js**: Error handling components
  - **not-found.js**: 404 page component
  - **globals.css**: Global styles

### Pages Router Structure (Legacy)
- **pages/**: File-based routing directory
  - **_app.js**: Custom App component
  - **_document.js**: Custom Document component
  - **index.js**: Home page
  - **api/**: API routes directory

### Common Directories
- **public/**: Static files served from root
  - **images/**: Image assets
  - **favicon.ico**: Website favicon

- **components/**: Reusable React components
- **lib/**: Utility functions and configurations
- **styles/**: Global styles and CSS modules
- **hooks/**: Custom React hooks
- **utils/**: Helper functions
- **types/**: TypeScript type definitions

## Key Next.js Features

### Rendering Methods
Multiple rendering strategies for optimal performance:
- **Static Site Generation (SSG)**: Pre-rendered at build time
- **Server-Side Rendering (SSR)**: Rendered on each request
- **Incremental Static Regeneration (ISR)**: Update static content after deployment
- **Client-Side Rendering (CSR)**: Rendered in the browser

### Routing
File-based routing system:
- **Automatic Routing**: Files in pages/ become routes
- **Dynamic Routes**: [id].js for parameter-based routes
- **API Routes**: Backend API endpoints in pages/api/
- **Nested Routing**: Directory structure maps to URL structure

### Performance Optimization
Built-in performance features:
- **Image Optimization**: next/image component with automatic optimization
- **Font Optimization**: next/font for web font optimization
- **Code Splitting**: Automatic bundle splitting
- **Prefetching**: Link prefetching for faster navigation

### Data Fetching
Multiple data fetching patterns:
- **getStaticProps**: Fetch data at build time (SSG)
- **getServerSideProps**: Fetch data on each request (SSR)
- **getStaticPaths**: Generate dynamic routes at build time
- **SWR/React Query**: Client-side data fetching

### Styling
Flexible styling options:
- **CSS Modules**: Scoped CSS
- **Styled JSX**: Built-in CSS-in-JS
- **Tailwind CSS**: Utility-first CSS framework
- **SASS/SCSS**: CSS preprocessing
- **CSS-in-JS**: Styled Components, Emotion

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

        $content .= "\nGenerated automatically by CTX for Next.js projects.\n";

        return $content;
    }

    /**
     * Get Next.js version from package.json
     */
    private function getNextVersion(?array $packageJson = null): ?string
    {
        if ($packageJson === null) {
            return null;
        }

        return $packageJson['dependencies']['next'] ?? $packageJson['devDependencies']['next'] ?? null;
    }

    /**
     * Detect if project uses App Router
     */
    private function detectAppRouter(array $projectMetadata): bool
    {
        $existingDirs = $projectMetadata['existingDirectories'] ?? [];

        return \in_array('app', $existingDirs, true);
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
