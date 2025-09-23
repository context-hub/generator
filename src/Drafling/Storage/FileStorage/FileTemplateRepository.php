<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Storage\FileStorage;

use Butschster\ContextGenerator\Drafling\Domain\Model\Category;
use Butschster\ContextGenerator\Drafling\Domain\Model\EntryType;
use Butschster\ContextGenerator\Drafling\Domain\Model\Status;
use Butschster\ContextGenerator\Drafling\Domain\Model\Template;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\TemplateKey;
use Butschster\ContextGenerator\Drafling\Repository\TemplateRepositoryInterface;

/**
 * File-based template repository implementation
 */
final class FileTemplateRepository extends FileStorageRepositoryBase implements TemplateRepositoryInterface
{
    private array $templateCache = [];
    private bool $cacheLoaded = false;

    #[\Override]
    public function findAll(): array
    {
        $this->ensureCacheLoaded();
        return \array_values($this->templateCache);
    }

    #[\Override]
    public function findByKey(TemplateKey $key): ?Template
    {
        $this->ensureCacheLoaded();
        return $this->templateCache[$key->value] ?? null;
    }

    #[\Override]
    public function exists(TemplateKey $key): bool
    {
        return $this->findByKey($key) !== null;
    }

    #[\Override]
    public function refresh(): void
    {
        $this->templateCache = [];
        $this->cacheLoaded = false;
        $this->logOperation('Template cache refreshed');
    }

    /**
     * Load all templates from file system if not already cached
     */
    private function ensureCacheLoaded(): void
    {
        if ($this->cacheLoaded) {
            return;
        }

        $this->loadTemplatesFromFilesystem();
        $this->cacheLoaded = true;
    }

    /**
     * Load templates from YAML files in templates directory
     */
    private function loadTemplatesFromFilesystem(): void
    {
        $templatesPath = $this->getTemplatesPath();

        if (!$this->files->exists($templatesPath) || !$this->files->isDirectory($templatesPath)) {
            $this->logger?->warning('Templates directory not found', ['path' => $templatesPath]);
            return;
        }

        $templateFiles = $this->files->getFiles($templatesPath, '*.yaml');

        foreach ($templateFiles as $templateFile) {
            $filePath = $this->files->normalizePath($templatesPath . '/' . $templateFile);

            try {
                $template = $this->loadTemplateFromFile($filePath);
                if ($template !== null) {
                    $this->templateCache[$template->key] = $template;
                }
            } catch (\Throwable $e) {
                $this->logError('Failed to load template', ['file' => $filePath], $e);
            }
        }

        $this->logOperation('Loaded templates from filesystem', [
            'count' => \count($this->templateCache),
            'path' => $templatesPath,
        ]);
    }

    /**
     * Load template from individual YAML file
     */
    private function loadTemplateFromFile(string $filePath): ?Template
    {
        try {
            $templateData = $this->readYamlFile($filePath);
            return $this->createTemplateFromData($templateData);
        } catch (\Throwable $e) {
            $this->logError("Failed to load template from file: {$filePath}", [], $e);
            return null;
        }
    }

    /**
     * Create Template object from parsed YAML data
     */
    private function createTemplateFromData(array $data): Template
    {
        // Validate required fields
        $requiredFields = ['key', 'name', 'description'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new \RuntimeException("Missing required template field: {$field}");
            }
        }

        // Parse categories
        $categories = [];
        if (isset($data['categories']) && \is_array($data['categories'])) {
            foreach ($data['categories'] as $categoryData) {
                $categories[] = $this->createCategoryFromData($categoryData);
            }
        }

        // Parse entry types
        $entryTypes = [];
        if (isset($data['entry_types']) && \is_array($data['entry_types'])) {
            foreach ($data['entry_types'] as $key => $entryTypeData) {
                $entryTypes[] = $this->createEntryTypeFromData($key, $entryTypeData);
            }
        }

        return new Template(
            key: $data['key'],
            name: $data['name'],
            description: $data['description'],
            tags: $data['tags'] ?? [],
            categories: $categories,
            entryTypes: $entryTypes,
            prompt: $data['prompt'] ?? null,
        );
    }

    /**
     * Create Category object from parsed data
     */
    private function createCategoryFromData(array $data): Category
    {
        $requiredFields = ['name', 'display_name', 'icon', 'entry_types'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new \RuntimeException("Missing required category field: {$field}");
            }
        }

        return new Category(
            name: $data['name'],
            displayName: $data['display_name'],
            icon: $data['icon'],
            entryTypes: $data['entry_types'],
        );
    }

    /**
     * Create EntryType object from parsed data
     */
    private function createEntryTypeFromData(string $key, array $data): EntryType
    {
        $requiredFields = ['display_name', 'icon', 'content_type', 'color', 'default_status'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new \RuntimeException("Missing required entry type field: {$field}");
            }
        }

        // Parse statuses
        $statuses = [];
        if (isset($data['statuses']) && \is_array($data['statuses'])) {
            foreach ($data['statuses'] as $statusData) {
                $statuses[] = $this->createStatusFromData($statusData);
            }
        }

        return new EntryType(
            key: $key,
            displayName: $data['display_name'],
            icon: $data['icon'],
            contentType: $data['content_type'],
            color: $data['color'],
            defaultStatus: $data['default_status'],
            statuses: $statuses,
        );
    }

    /**
     * Create Status object from parsed data
     */
    private function createStatusFromData(array $data): Status
    {
        $requiredFields = ['value', 'display_name', 'color'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new \RuntimeException("Missing required status field: {$field}");
            }
        }

        return new Status(
            value: $data['value'],
            displayName: $data['display_name'],
            color: $data['color'],
        );
    }
}
