<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\Storage\FileStorage;

use Butschster\ContextGenerator\Research\Domain\Model\Entry;
use Butschster\ContextGenerator\Research\Domain\ValueObject\EntryId;
use Butschster\ContextGenerator\Research\Domain\ValueObject\ResearchId;
use Butschster\ContextGenerator\Research\Repository\EntryRepositoryInterface;

/**
 * File-based entry repository implementation
 */
final class FileEntryRepository extends FileStorageRepositoryBase implements EntryRepositoryInterface
{
    #[\Override]
    public function findByResearch(ResearchId $researchId, array $filters = []): array
    {
        $researchPath = $this->getResearchPath($researchId->value);

        if (!$this->files->exists($researchPath)) {
            return [];
        }

        $entries = [];

        try {
            $entryFiles = $this->directoryScanner->scanEntries($researchPath);

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

            $this->logOperation('Loaded entries for research', [
                'research_id' => $researchId->value,
                'count' => \count($entries),
                'total_scanned' => \count($entryFiles),
            ]);
        } catch (\Throwable $e) {
            $this->logError('Failed to scan entries for research', ['research_id' => $researchId->value], $e);
        }

        return $entries;
    }

    #[\Override]
    public function findById(ResearchId $researchId, EntryId $entryId): ?Entry
    {
        $researchPath = $this->getResearchPath($researchId->value);
        $entryFile = $this->findEntryFile($researchPath, $entryId->value);

        if ($entryFile === null) {
            return null;
        }

        try {
            return $this->loadEntryFromFile($entryFile);
        } catch (\Throwable $e) {
            $this->logError('Failed to load entry by ID', [
                'research_id' => $researchId->value,
                'entry_id' => $entryId->value,
            ], $e);
            return null;
        }
    }

    #[\Override]
    public function save(ResearchId $researchId, Entry $entry): void
    {
        $researchPath = $this->getResearchPath($researchId->value);

        if (!$this->files->exists($researchPath)) {
            throw new \RuntimeException("Research directory not found: {$researchPath}");
        }

        try {
            $this->saveEntryToFile($researchPath, $entry);

            $this->logOperation('Saved entry', [
                'research_id' => $researchId->value,
                'entry_id' => $entry->entryId,
                'title' => $entry->title,
            ]);
        } catch (\Throwable $e) {
            $this->logError('Failed to save entry', [
                'research_id' => $researchId->value,
                'entry_id' => $entry->entryId,
            ], $e);
            throw $e;
        }
    }

    #[\Override]
    public function delete(ResearchId $researchId, EntryId $entryId): bool
    {
        $researchPath = $this->getResearchPath($researchId->value);
        $entryFile = $this->findEntryFile($researchPath, $entryId->value);

        if ($entryFile === null) {
            return false;
        }

        try {
            $deleted = $this->files->delete($entryFile);

            if ($deleted) {
                $this->logOperation('Deleted entry', [
                    'research_id' => $researchId->value,
                    'entry_id' => $entryId->value,
                    'file' => $entryFile,
                ]);
            }

            return $deleted;
        } catch (\Throwable $e) {
            $this->logError('Failed to delete entry', [
                'research_id' => $researchId->value,
                'entry_id' => $entryId->value,
            ], $e);
            return false;
        }
    }

    #[\Override]
    public function exists(ResearchId $researchId, EntryId $entryId): bool
    {
        $researchPath = $this->getResearchPath($researchId->value);
        return $this->findEntryFile($researchPath, $entryId->value) !== null;
    }

    /**
     * Get research directory path from ID
     */
    private function getResearchPath(string $researchId): string
    {
        $basePath = $this->getBasePath();
        return $this->files->normalizePath($basePath . '/' . $researchId);
    }

    /**
     * Find entry file by entry ID
     */
    private function findEntryFile(string $researchPath, string $entryId): ?string
    {
        $entryFiles = $this->directoryScanner->scanEntries($researchPath);

        foreach ($entryFiles as $filePath) {
            try {
                $frontmatter = $this->frontmatterParser->extractFrontmatter(
                    $this->files->read($filePath),
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
                description: $frontmatter['description'] ?? '', // Default to empty if not present in file
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
    private function saveEntryToFile(string $researchPath, Entry $entry): void
    {
        // Determine file path
        $filePath = $entry->filePath;

        if ($filePath === null) {
            // New entry - generate file path
            $categoryPath = $this->files->normalizePath($researchPath . '/' . $entry->category . '/' . $entry->entryType);
            $this->ensureDirectory($categoryPath);

            $filename = $this->generateFilename($entry->title);
            $filePath = $categoryPath . '/' . $filename;
        }

        // Prepare frontmatter
        $frontmatter = [
            'entry_id' => $entry->entryId,
            'title' => $entry->title,
            'description' => $entry->description,
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

        // Title contains filter
        if (isset($filters['title_contains']) && \is_string($filters['title_contains'])) {
            if (\stripos($entry->title, $filters['title_contains']) === false) {
                return false;
            }
        }

        // Description contains filter
        if (isset($filters['description_contains']) && \is_string($filters['description_contains'])) {
            if (\stripos($entry->description, $filters['description_contains']) === false) {
                return false;
            }
        }

        // Content contains filter
        if (isset($filters['content_contains']) && \is_string($filters['content_contains'])) {
            if (\stripos($entry->content, $filters['content_contains']) === false) {
                return false;
            }
        }

        return true;
    }
}
