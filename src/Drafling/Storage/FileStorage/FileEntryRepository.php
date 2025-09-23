<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Storage\FileStorage;

use Butschster\ContextGenerator\Drafling\Domain\Model\Entry;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\EntryId;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\ProjectId;
use Butschster\ContextGenerator\Drafling\Repository\EntryRepositoryInterface;
use Butschster\ContextGenerator\Drafling\Exception\EntryNotFoundException;

/**
 * File-based entry repository implementation
 */
final class FileEntryRepository extends FileStorageRepositoryBase implements EntryRepositoryInterface
{
    #[\Override]
    public function findByProject(ProjectId $projectId, array $filters = []): array
    {
        $projectPath = $this->getProjectPath($projectId->value);
        
        if (!$this->files->exists($projectPath)) {
            return [];
        }

        $entries = [];
        
        try {
            // Get entry directories from project or scan all directories
            $entryDirs = $this->getProjectEntryDirs($projectPath);
            $entryFiles = $this->directoryScanner->scanEntries($projectPath, $entryDirs);

            foreach ($entryFiles as $filePath) {
                try {
                    $entry = $this->loadEntryFromFile($filePath);
                    if ($entry !== null && $this->matchesFilters($entry, $filters)) {
                        $entries[] = $entry;
                    }
                } catch (\Throwable $e) {
                    $this->logError('Failed to load entry', ['file' => $filePath], $e);
                }
            }

            $this->logOperation('Loaded entries for project', [
                'project_id' => $projectId->value,
                'count' => \count($entries),
                'total_scanned' => \count($entryFiles),
            ]);
        } catch (\Throwable $e) {
            $this->logError('Failed to scan entries for project', ['project_id' => $projectId->value], $e);
        }

        return $entries;
    }

    #[\Override]
    public function findById(ProjectId $projectId, EntryId $entryId): ?Entry
    {
        $projectPath = $this->getProjectPath($projectId->value);
        $entryFile = $this->findEntryFile($projectPath, $entryId->value);
        
        if ($entryFile === null) {
            return null;
        }

        try {
            return $this->loadEntryFromFile($entryFile);
        } catch (\Throwable $e) {
            $this->logError('Failed to load entry by ID', [
                'project_id' => $projectId->value,
                'entry_id' => $entryId->value,
            ], $e);
            return null;
        }
    }

    #[\Override]
    public function save(ProjectId $projectId, Entry $entry): void
    {
        $projectPath = $this->getProjectPath($projectId->value);
        
        if (!$this->files->exists($projectPath)) {
            throw new \RuntimeException("Project directory not found: {$projectPath}");
        }

        try {
            $this->saveEntryToFile($projectPath, $entry);
            
            $this->logOperation('Saved entry', [
                'project_id' => $projectId->value,
                'entry_id' => $entry->entryId,
                'title' => $entry->title,
            ]);
        } catch (\Throwable $e) {
            $this->logError('Failed to save entry', [
                'project_id' => $projectId->value,
                'entry_id' => $entry->entryId,
            ], $e);
            throw $e;
        }
    }

    #[\Override]
    public function delete(ProjectId $projectId, EntryId $entryId): bool
    {
        $projectPath = $this->getProjectPath($projectId->value);
        $entryFile = $this->findEntryFile($projectPath, $entryId->value);
        
        if ($entryFile === null) {
            return false;
        }

        try {
            $deleted = $this->files->delete($entryFile);
            
            if ($deleted) {
                $this->logOperation('Deleted entry', [
                    'project_id' => $projectId->value,
                    'entry_id' => $entryId->value,
                    'file' => $entryFile,
                ]);
            }

            return $deleted;
        } catch (\Throwable $e) {
            $this->logError('Failed to delete entry', [
                'project_id' => $projectId->value,
                'entry_id' => $entryId->value,
            ], $e);
            return false;
        }
    }

    #[\Override]
    public function exists(ProjectId $projectId, EntryId $entryId): bool
    {
        $projectPath = $this->getProjectPath($projectId->value);
        return $this->findEntryFile($projectPath, $entryId->value) !== null;
    }

    /**
     * Get project directory path from ID
     */
    private function getProjectPath(string $projectId): string
    {
        $basePath = $this->getBasePath();
        return $this->files->normalizePath($basePath . '/' . $projectId);
    }

    /**
     * Get entry directories for a project
     */
    private function getProjectEntryDirs(string $projectPath): array
    {
        $configPath = $projectPath . '/project.yaml';
        
        if (!$this->files->exists($configPath)) {
            // Fallback: scan all directories
            return $this->directoryScanner->getEntryDirectories($projectPath);
        }

        try {
            $config = $this->readYamlFile($configPath);
            return $config['project']['entries']['dirs'] ?? [];
        } catch (\Throwable) {
            // Fallback: scan all directories
            return $this->directoryScanner->getEntryDirectories($projectPath);
        }
    }

    /**
     * Find entry file by entry ID
     */
    private function findEntryFile(string $projectPath, string $entryId): ?string
    {
        $entryDirs = $this->getProjectEntryDirs($projectPath);
        $entryFiles = $this->directoryScanner->scanEntries($projectPath, $entryDirs);

        foreach ($entryFiles as $filePath) {
            try {
                $frontmatter = $this->frontmatterParser->extractFrontmatter(
                    $this->files->read($filePath)
                );

                if (isset($frontmatter['entry_id']) && $frontmatter['entry_id'] === $entryId) {
                    return $filePath;
                }
            } catch (\Throwable $e) {
                $this->logError('Failed to check entry file', ['file' => $filePath], $e);
            }
        }

        return null;
    }

    /**
     * Load entry from markdown file
     */
    private function loadEntryFromFile(string $filePath): ?Entry
    {
        try {
            $parsed = $this->readMarkdownFile($filePath);
            $frontmatter = $parsed['frontmatter'];
            $content = $parsed['content'];

            // Validate required frontmatter fields
            $requiredFields = ['entry_id', 'title', 'entry_type', 'category', 'status'];
            foreach ($requiredFields as $field) {
                if (!isset($frontmatter[$field])) {
                    throw new \RuntimeException("Missing required frontmatter field: {$field}");
                }
            }

            // Parse dates
            $createdAt = isset($frontmatter['created_at']) 
                ? new \DateTime($frontmatter['created_at'])
                : new \DateTime();
                
            $updatedAt = isset($frontmatter['updated_at']) 
                ? new \DateTime($frontmatter['updated_at'])
                : new \DateTime();

            return new Entry(
                entryId: $frontmatter['entry_id'],
                title: $frontmatter['title'],
                entryType: $frontmatter['entry_type'],
                category: $frontmatter['category'],
                status: $frontmatter['status'],
                createdAt: $createdAt,
                updatedAt: $updatedAt,
                tags: $frontmatter['tags'] ?? [],
                content: $content,
                filePath: $filePath,
            );
        } catch (\Throwable $e) {
            $this->logError("Failed to load entry from file: {$filePath}", [], $e);
            return null;
        }
    }

    /**
     * Save entry to markdown file
     */
    private function saveEntryToFile(string $projectPath, Entry $entry): void
    {
        // Determine file path
        $filePath = $entry->filePath;
        
        if ($filePath === null) {
            // New entry - generate file path
            $categoryPath = $this->files->normalizePath($projectPath . '/' . $entry->category);
            $this->ensureDirectory($categoryPath);
            
            $filename = $this->generateFilename($entry->title);
            $filePath = $categoryPath . '/' . $filename;
        }

        // Prepare frontmatter
        $frontmatter = [
            'entry_id' => $entry->entryId,
            'title' => $entry->title,
            'entry_type' => $entry->entryType,
            'category' => $entry->category,
            'status' => $entry->status,
            'created_at' => $entry->createdAt->format('c'),
            'updated_at' => $entry->updatedAt->format('c'),
            'tags' => $entry->tags,
        ];

        // Write markdown file
        $this->writeMarkdownFile($filePath, $frontmatter, $entry->content);
    }

    /**
     * Check if entry matches the provided filters
     */
    private function matchesFilters(Entry $entry, array $filters): bool
    {
        // Category filter
        if (isset($filters['category']) && $entry->category !== $filters['category']) {
            return false;
        }

        // Status filter
        if (isset($filters['status']) && $entry->status !== $filters['status']) {
            return false;
        }

        // Entry type filter
        if (isset($filters['entry_type']) && $entry->entryType !== $filters['entry_type']) {
            return false;
        }

        // Tags filter (any of the provided tags should match)
        if (isset($filters['tags']) && \is_array($filters['tags'])) {
            $hasMatchingTag = false;
            foreach ($filters['tags'] as $filterTag) {
                if (\in_array($filterTag, $entry->tags, true)) {
                    $hasMatchingTag = true;
                    break;
                }
            }
            if (!$hasMatchingTag) {
                return false;
            }
        }

        return true;
    }
}
