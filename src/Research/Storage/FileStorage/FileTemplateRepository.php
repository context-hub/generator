<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\Storage\FileStorage;

use Butschster\ContextGenerator\Research\Domain\Model\Category;
use Butschster\ContextGenerator\Research\Domain\Model\EntryType;
use Butschster\ContextGenerator\Research\Domain\Model\Status;
use Butschster\ContextGenerator\Research\Domain\Model\Template;
use Butschster\ContextGenerator\Research\Domain\ValueObject\TemplateKey;
use Butschster\ContextGenerator\Research\Repository\TemplateRepositoryInterface;
use Symfony\Component\Finder\Finder;

/**
 * File-based template repository implementation
 */
final class FileTemplateRepository extends FileStorageRepositoryBase implements TemplateRepositoryInterface
{
    #[\Override]
    public function findAll(): array
    {
        return $this->loadTemplatesFromFilesystem();
    }

    #[\Override]
    public function findByKey(TemplateKey $key): ?Template
    {
        $templates = $this->loadTemplatesFromFilesystem();

        foreach ($templates as $template) {
            if ($template->key === $key->value) {
                return $template;
            }
        }

        return null;
    }

    #[\Override]
    public function exists(TemplateKey $key): bool
    {
        return $this->findByKey($key) !== null;
    }

    #[\Override]
    public function refresh(): void
    {
        // No-op since we don't cache anymore
        $this->logOperation('Template refresh requested (no caching)');
    }

    /**
     * Load templates from YAML files in templates directory
     */
    private function loadTemplatesFromFilesystem(): array
    {
        $templatesPath = $this->getTemplatesPath();

        $templates = [];

        if (!$this->files->exists($templatesPath) || !$this->files->isDirectory($templatesPath)) {
            $this->logger?->warning('Templates directory not found', ['path' => $templatesPath]);
            return $templates;
        }

        $finder = new Finder();
        $finder->files()
            ->in($templatesPath)
            ->name('*.yaml')
            ->name('*.yml');

        foreach ($finder as $file) {
            try {
                $template = $this->loadTemplateFromFile($file->getRealPath());
                if ($template !== null) {
                    $templates[] = $template;
                }
            } catch (\Throwable $e) {
                $this->reporter->report($e);
                $this->logError('Failed to load template', ['file' => $file->getRealPath()], $e);
            }
        }

        $this->logOperation('Loaded templates from filesystem', [
            'count' => \count($templates),
            'path' => $templatesPath,
        ]);

        return $templates;
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
            $this->reporter->report($e);
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
        $requiredFields = ['name', 'display_name', 'entry_types'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new \RuntimeException("Missing required category field: {$field}");
            }
        }

        return new Category(
            name: $data['name'],
            displayName: $data['display_name'],
            entryTypes: $data['entry_types'],
        );
    }

    /**
     * Create EntryType object from parsed data
     */
    private function createEntryTypeFromData(string $key, array $data): EntryType
    {
        $requiredFields = ['display_name'];
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
            contentType: $data['content_type'] ?? 'markdown',
            defaultStatus: $data['default_status'] ?? 'draft',
            statuses: $statuses,
        );
    }

    /**
     * Create Status object from parsed data
     */
    private function createStatusFromData(array $data): Status
    {
        $requiredFields = ['value', 'display_name'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new \RuntimeException("Missing required status field: {$field}");
            }
        }

        return new Status(
            value: $data['value'],
            displayName: $data['display_name'],
        );
    }
}
