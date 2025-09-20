<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Definition;

use Butschster\ContextGenerator\Config\Registry\ConfigRegistry;
use Butschster\ContextGenerator\Document\Document;
use Butschster\ContextGenerator\Document\DocumentRegistry;
use Butschster\ContextGenerator\Lib\TreeBuilder\TreeViewConfig;
use Butschster\ContextGenerator\Source\Tree\TreeSource;
use Butschster\ContextGenerator\Template\Template;

/**
 * Abstract base class for template definitions
 * Provides common functionality and enforces consistent structure
 */
abstract class AbstractTemplateDefinition implements TemplateDefinitionInterface
{
    final public function createTemplate(array $projectMetadata = []): Template
    {
        $config = new ConfigRegistry();
        $documents = new DocumentRegistry($this->createDocuments($projectMetadata));
        $config->register($documents);

        return new Template(
            name: $this->getName(),
            description: $this->getDescription(),
            tags: $this->getTags(),
            priority: $this->getPriority(),
            detectionCriteria: $this->buildDetectionCriteria(),
            config: $config,
        );
    }

    /**
     * Get detection criteria for automatic selection
     */
    final public function getDetectionCriteria(): array
    {
        return $this->buildDetectionCriteria();
    }

    /**
     * Get the main source directories for this template type
     *
     * @return array<string>
     */
    abstract protected function getSourceDirectories(): array;

    /**
     * Get framework-specific detection criteria
     *
     * @return array<string, mixed>
     */
    abstract protected function getFrameworkSpecificCriteria(): array;

    /**
     * Get the output filename for the structure document
     */
    protected function getStructureDocumentPath(): string
    {
        return 'docs/' . $this->getName() . '-structure.md';
    }

    /**
     * Get the description for the structure document
     */
    protected function getStructureDocumentDescription(): string
    {
        return $this->getDescription() . ' - Project Structure';
    }

    /**
     * Get source paths filtered by existing directories in project metadata
     *
     * @return array<string>
     */
    protected function getDetectedSourcePaths(array $projectMetadata): array
    {
        $existingDirs = $projectMetadata['existingDirectories'] ?? $projectMetadata['directories'] ?? [];
        $sourceDirs = $this->getSourceDirectories();

        // Filter to only include directories that exist
        $detectedPaths = \array_intersect($existingDirs, $sourceDirs);

        // If no standard directories found, return all expected directories
        if (empty($detectedPaths)) {
            return $sourceDirs;
        }

        return \array_values($detectedPaths);
    }

    /**
     * Create additional documents beyond the standard structure document
     * Override in subclasses to add framework-specific documents
     *
     * @return array<Document>
     */
    protected function createAdditionalDocuments(array $projectMetadata): array
    {
        return [];
    }

    /**
     * Create the tree view configuration for structure documents
     */
    protected function createTreeViewConfig(): TreeViewConfig
    {
        return new TreeViewConfig(
            showCharCount: true,
            includeFiles: true,
            maxDepth: 3,
        );
    }

    /**
     * Customize tree view configuration in subclasses if needed
     */
    protected function customizeTreeViewConfig(TreeViewConfig $config): TreeViewConfig
    {
        return $config;
    }

    /**
     * Create all documents for this template
     *
     * @return array<Document>
     */
    protected function createDocuments(array $projectMetadata): array
    {
        return [
            $this->createStructureDocument($projectMetadata),
            ...$this->createAdditionalDocuments($projectMetadata),
        ];
    }

    /**
     * Create the main structure document
     */
    protected function createStructureDocument(array $projectMetadata): Document
    {
        $sourcePaths = $this->getDetectedSourcePaths($projectMetadata);
        $treeViewConfig = $this->customizeTreeViewConfig($this->createTreeViewConfig());

        return new Document(
            description: $this->getStructureDocumentDescription(),
            outputPath: $this->getStructureDocumentPath(),
            overwrite: true,
            modifiers: [],
            tags: [],
            treeSource: new TreeSource(
                sourcePaths: $sourcePaths,
                description: $this->getName() . ' Directory Structure',
                treeView: $treeViewConfig,
            ),
        );
    }

    /**
     * Build complete detection criteria by merging common and framework-specific criteria
     */
    private function buildDetectionCriteria(): array
    {
        return \array_merge([
            'directories' => $this->getSourceDirectories(),
        ], $this->getFrameworkSpecificCriteria());
    }
}
