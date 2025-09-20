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
 * Vue3 project template definition
 */
final class VueTemplateDefinition implements TemplateDefinitionInterface
{
    /**
     * Vue-specific directories that indicate a Vue project
     */
    private const array VUE_DIRECTORIES = [
        'src',
        'public',
        'components',
        'views',
        'assets',
    ];

    /**
     * Vue-specific packages in package.json
     */
    private const array VUE_PACKAGES = [
        'vue',
        '@vue/cli-service',
        'vite',
        '@vitejs/plugin-vue',
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
        return 'vue';
    }

    public function getDescription(): string
    {
        return 'Vue3 application project template';
    }

    public function getTags(): array
    {
        return ['javascript', 'vue', 'vue3', 'frontend', 'spa'];
    }

    public function getPriority(): int
    {
        return 85; // High priority for specific framework detection
    }

    public function getDetectionCriteria(): array
    {
        return [
            'files' => ['package.json'],
            'patterns' => self::VUE_PACKAGES,
            'directories' => self::VUE_DIRECTORIES,
        ];
    }

    /**
     * Check if this definition can detect a Vue project
     */
    public function canDetect(array $projectMetadata): bool
    {
        $packageJson = $projectMetadata['packageJson'] ?? null;

        if ($packageJson === null) {
            return false;
        }

        // Check for Vue-specific packages
        foreach (self::VUE_PACKAGES as $package) {
            if ($this->hasPackage($packageJson, $package)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create the Vue application overview document
     */
    private function createOverviewDocument(array $projectMetadata): Document
    {
        return new Document(
            description: 'Vue Application Overview',
            outputPath: 'docs/vue-overview.md',
            overwrite: true,
            modifiers: [],
            tags: [],
            textSource: new TextSource(
                content: $this->generateOverviewContent($projectMetadata),
                description: 'Vue Overview Header',
            ),
        );
    }

    /**
     * Create the Vue project structure document
     */
    private function createStructureDocument(array $projectMetadata): Document
    {
        return new Document(
            description: 'Vue Project Structure',
            outputPath: 'docs/vue-structure.md',
            overwrite: true,
            modifiers: [],
            tags: [],
            treeSource: new TreeSource(
                sourcePaths: ['src', 'public'],
                description: 'Vue Directory Structure',
                treeView: new TreeViewConfig(
                    showCharCount: true,
                    includeFiles: true,
                    maxDepth: 3,
                ),
            ),
        );
    }

    /**
     * Generate overview content with Vue-specific information
     */
    private function generateOverviewContent(array $projectMetadata): string
    {
        $packageJson = $projectMetadata['packageJson'] ?? null;
        $vueVersion = $this->getVueVersion($packageJson);

        $content = <<<'CONTENT'
# Vue Application Context

This document provides a comprehensive overview of the Vue application structure and key components.

CONTENT;

        // Add Vue version information if available
        if ($vueVersion !== null) {
            $content .= "**Vue Version:** `{$vueVersion}`\n\n";
        }

        $content .= <<<'CONTENT'
## Project Structure

This Vue application follows modern Vue 3 development practices:

- **src/**: Main source code directory
  - **components/**: Reusable Vue components
  - **views/**: Page-level components
  - **composables/**: Composition API reusable logic
  - **stores/**: Pinia/Vuex store modules
  - **router/**: Vue Router configuration
  - **services/**: API and external service integrations
  - **utils/**: Utility functions and helpers
  - **types/**: TypeScript type definitions (if using TypeScript)
  - **assets/**: Images, fonts, and other static assets
  - **styles/**: Global CSS/SCSS files

- **public/**: Static files served directly
  - **index.html**: Main HTML template
  - **favicon.ico**: Website favicon

- **dist/**: Production build output (generated)
- **node_modules/**: NPM dependencies
- **package.json**: Project configuration and dependencies

## Key Vue 3 Concepts

### Composition API
Modern way to compose component logic:
- **setup()**: Entry point for composition logic
- **Reactivity**: ref, reactive, computed
- **Lifecycle**: onMounted, onUpdated, onUnmounted
- **Composables**: Reusable stateful logic

### Components
Building blocks of Vue applications:
- **Single File Components**: .vue files with template, script, and style
- **Props**: Parent-to-child data flow
- **Emits**: Child-to-parent communication
- **Slots**: Content distribution

### Reactivity System
Vue's core reactivity features:
- **ref()**: Creating reactive references
- **reactive()**: Creating reactive objects
- **computed()**: Computed properties
- **watch()**: Watching reactive data

### State Management
Centralized state management solutions:
- **Pinia**: Modern state management for Vue 3
- **Vuex**: Traditional state management (Vue 2 compatible)
- **Provide/Inject**: Simple state sharing

### Routing
Client-side routing for single-page applications:
- **Vue Router 4**: Official router for Vue 3
- **Dynamic Routing**: Route parameters and queries
- **Navigation Guards**: Route protection and logic
- **Lazy Loading**: Code splitting by routes

### Build Tools
Modern development and build tooling:
- **Vite**: Fast build tool and dev server
- **Vue CLI**: Traditional Vue project tooling
- **Webpack**: Module bundling (legacy)

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

        $content .= "\nGenerated automatically by CTX for Vue projects.\n";

        return $content;
    }

    /**
     * Get Vue version from package.json
     */
    private function getVueVersion(?array $packageJson = null): ?string
    {
        if ($packageJson === null) {
            return null;
        }

        return $packageJson['dependencies']['vue'] ?? $packageJson['devDependencies']['vue'] ?? null;
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
