<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Builder;

use Butschster\ContextGenerator\Config\Registry\ConfigRegistry;
use Butschster\ContextGenerator\Document\Document;
use Butschster\ContextGenerator\Document\DocumentRegistry;
use Butschster\ContextGenerator\Lib\TreeBuilder\TreeViewConfig;
use Butschster\ContextGenerator\Source\File\FileSource;
use Butschster\ContextGenerator\Source\Tree\TreeSource;

/**
 * Fluent builder for template configurations
 * Provides a clean API for constructing template documents and sources
 */
final class TemplateConfigurationBuilder
{
    private array $documents = [];
    private array $detectionCriteria = [];

    public function __construct(private readonly string $templateName) {}

    /**
     * Add a structure document showing project directory tree
     */
    public function addStructureDocument(
        array $sourcePaths,
        ?string $outputPath = null,
        ?string $description = null,
        ?TreeViewConfig $treeViewConfig = null,
    ): self {
        $outputPath ??= 'docs/' . \strtolower($this->templateName) . '-structure.md';
        $description ??= \ucfirst($this->templateName) . ' Project Structure';
        $treeViewConfig ??= new TreeViewConfig(
            showCharCount: true,
            includeFiles: true,
            maxDepth: 3,
        );

        $this->documents[] = new Document(
            description: $description,
            outputPath: $outputPath,
            overwrite: true,
            modifiers: [],
            tags: [\strtolower($this->templateName), 'structure'],
            treeSource: new TreeSource(
                sourcePaths: $sourcePaths,
                description: \ucfirst($this->templateName) . ' Directory Structure',
                treeView: $treeViewConfig,
            ),
        );

        return $this;
    }

    /**
     * Add a source code document showing files with optional modifiers
     */
    public function addSourceDocument(
        string $description,
        string $outputPath,
        array $sourcePaths,
        array $filePatterns = ['*.php'],
        array $modifiers = [],
        array $tags = [],
    ): self {
        $this->documents[] = new Document(
            description: $description,
            outputPath: $outputPath,
            overwrite: true,
            modifiers: $modifiers,
            tags: \array_merge([\strtolower($this->templateName)], $tags),
            fileSource: new FileSource(
                sourcePaths: $sourcePaths,
                description: $description,
                filePattern: $filePatterns,
                modifiers: $modifiers,
            ),
        );

        return $this;
    }

    /**
     * Add custom document with full control
     */
    public function addDocument(Document $document): self
    {
        $this->documents[] = $document;
        return $this;
    }

    /**
     * Set required files for template detection
     */
    public function requireFiles(array $files): self
    {
        $this->detectionCriteria['files'] = \array_merge(
            $this->detectionCriteria['files'] ?? [],
            $files,
        );
        return $this;
    }

    /**
     * Set required directories for template detection
     */
    public function requireDirectories(array $directories): self
    {
        $this->detectionCriteria['directories'] = \array_merge(
            $this->detectionCriteria['directories'] ?? [],
            $directories,
        );
        return $this;
    }

    /**
     * Set required packages for template detection
     */
    public function requirePackages(array $packages): self
    {
        $this->detectionCriteria['patterns'] = \array_merge(
            $this->detectionCriteria['patterns'] ?? [],
            $packages,
        );
        return $this;
    }

    /**
     * Set complete detection criteria
     */
    public function setDetectionCriteria(array $criteria): self
    {
        $this->detectionCriteria = $criteria;
        return $this;
    }

    /**
     * Build the final configuration registry
     */
    public function build(): ConfigRegistry
    {
        $config = new ConfigRegistry();
        $documents = new DocumentRegistry($this->documents);
        $config->register($documents);
        return $config;
    }

    /**
     * Get the detection criteria that was built
     */
    public function getDetectionCriteria(): array
    {
        return $this->detectionCriteria;
    }

    /**
     * Get all documents that were added
     *
     * @return array<Document>
     */
    public function getDocuments(): array
    {
        return $this->documents;
    }

    /**
     * Clear all documents (useful for rebuilding)
     */
    public function clearDocuments(): self
    {
        $this->documents = [];
        return $this;
    }

    /**
     * Clear detection criteria
     */
    public function clearDetectionCriteria(): self
    {
        $this->detectionCriteria = [];
        return $this;
    }

    /**
     * Reset builder to initial state
     */
    public function reset(): self
    {
        $this->documents = [];
        $this->detectionCriteria = [];
        return $this;
    }
}
