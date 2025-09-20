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
 * React.js project template definition
 */
final class ReactTemplateDefinition implements TemplateDefinitionInterface
{
    /**
     * React-specific directories that indicate a React project
     */
    private const array REACT_DIRECTORIES = [
        'src',
        'public',
        'components',
        'pages',
    ];

    /**
     * React-specific packages in package.json
     */
    private const array REACT_PACKAGES = [
        'react',
        'react-dom',
        'react-scripts',
        '@types/react',
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
        return 'react';
    }

    public function getDescription(): string
    {
        return 'React.js application project template';
    }

    public function getTags(): array
    {
        return ['javascript', 'react', 'frontend', 'spa'];
    }

    public function getPriority(): int
    {
        return 85; // High priority for specific framework detection
    }

    public function getDetectionCriteria(): array
    {
        return [
            'files' => ['package.json'],
            'patterns' => self::REACT_PACKAGES,
            'directories' => self::REACT_DIRECTORIES,
        ];
    }

    /**
     * Check if this definition can detect a React project
     */
    public function canDetect(array $projectMetadata): bool
    {
        $packageJson = $projectMetadata['packageJson'] ?? null;

        if ($packageJson === null) {
            return false;
        }

        // Check for React-specific packages
        foreach (self::REACT_PACKAGES as $package) {
            if ($this->hasPackage($packageJson, $package)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create the React application overview document
     */
    private function createOverviewDocument(array $projectMetadata): Document
    {
        return new Document(
            description: 'React Application Overview',
            outputPath: 'docs/react-overview.md',
            overwrite: true,
            modifiers: [],
            tags: [],
            textSource: new TextSource(
                content: $this->generateOverviewContent($projectMetadata),
                description: 'React Overview Header',
            ),
        );
    }

    /**
     * Create the React project structure document
     */
    private function createStructureDocument(array $projectMetadata): Document
    {
        return new Document(
            description: 'React Project Structure',
            outputPath: 'docs/react-structure.md',
            overwrite: true,
            modifiers: [],
            tags: [],
            treeSource: new TreeSource(
                sourcePaths: ['src', 'public'],
                description: 'React Directory Structure',
                treeView: new TreeViewConfig(
                    showCharCount: true,
                    includeFiles: true,
                    maxDepth: 3,
                ),
            ),
        );
    }

    /**
     * Generate overview content with React-specific information
     */
    private function generateOverviewContent(array $projectMetadata): string
    {
        $packageJson = $projectMetadata['packageJson'] ?? null;
        $reactVersion = $this->getReactVersion($packageJson);

        $content = <<<'CONTENT'
# React Application Context

This document provides a comprehensive overview of the React application structure and key components.

CONTENT;

        // Add React version information if available
        if ($reactVersion !== null) {
            $content .= "**React Version:** `{$reactVersion}`\n\n";
        }

        $content .= <<<'CONTENT'
## Project Structure

This React application follows modern React development practices:

- **src/**: Main source code directory
  - **components/**: Reusable UI components
  - **pages/**: Top-level page components
  - **hooks/**: Custom React hooks
  - **services/**: API and external service integrations
  - **utils/**: Utility functions and helpers
  - **context/**: React Context providers
  - **types/**: TypeScript type definitions (if using TypeScript)
  - **styles/**: CSS/SCSS styling files
  - **assets/**: Images, fonts, and other static assets

- **public/**: Static files served directly
  - **index.html**: Main HTML template
  - **favicon.ico**: Website favicon
  - **manifest.json**: Progressive Web App manifest

- **build/**: Production build output (generated)
- **node_modules/**: NPM dependencies
- **package.json**: Project configuration and dependencies

## Key React Concepts

### Components
Building blocks of React applications:
- **Functional Components**: Modern approach using hooks
- **Class Components**: Legacy approach (still supported)
- **Higher-Order Components (HOCs)**: Component composition pattern
- **Render Props**: Component sharing pattern

### Hooks
Modern state and lifecycle management:
- **useState**: Local component state
- **useEffect**: Side effects and lifecycle
- **useContext**: Access React Context
- **useReducer**: Complex state management
- **Custom Hooks**: Reusable stateful logic

### State Management
Various approaches for managing application state:
- **Local State**: Component-level state with useState
- **Context API**: Sharing state across component tree
- **Redux**: Predictable state container
- **Zustand**: Lightweight state management
- **React Query**: Server state management

### Routing
Client-side routing for single-page applications:
- **React Router**: Declarative routing
- **Next.js Router**: File-based routing (if using Next.js)
- **Reach Router**: Alternative routing library

### Styling
Multiple approaches for styling components:
- **CSS Modules**: Scoped CSS
- **Styled Components**: CSS-in-JS
- **Emotion**: CSS-in-JS library
- **Tailwind CSS**: Utility-first CSS framework
- **SASS/SCSS**: CSS preprocessing

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

        $content .= "\nGenerated automatically by CTX for React projects.\n";

        return $content;
    }

    /**
     * Get React version from package.json
     */
    private function getReactVersion(?array $packageJson = null): ?string
    {
        if ($packageJson === null) {
            return null;
        }

        return $packageJson['dependencies']['react'] ?? $packageJson['devDependencies']['react'] ?? null;
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
