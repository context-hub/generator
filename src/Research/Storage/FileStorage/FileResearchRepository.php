<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\Storage\FileStorage;

use Butschster\ContextGenerator\Research\Domain\Model\Research;
use Butschster\ContextGenerator\Research\Domain\ValueObject\ResearchId;
use Butschster\ContextGenerator\Research\Repository\ResearchRepositoryInterface;

/**
 * File-based research repository implementation
 */
final class FileResearchRepository extends FileStorageRepositoryBase implements ResearchRepositoryInterface
{
    private const string CONFIG_FILE = 'research.yaml';

    #[\Override]
    public function findAll(array $filters = []): array
    {
        $researches = [];
        $researchPaths = $this->directoryScanner->scanResearches($this->getBasePath());

        foreach ($researchPaths as $researchPath) {
            try {
                $research = $this->loadResearchFromDirectory($researchPath);
                if ($research !== null && $this->matchesFilters($research, $filters)) {
                    $researches[] = $research;
                }
            } catch (\Throwable $e) {
                $this->logError('Failed to load research', ['path' => $researchPath], $e);
            }
        }

        $this->logOperation('Loaded researches', [
            'count' => \count($researches),
            'total_scanned' => \count($researchPaths),
        ]);

        return $researches;
    }

    #[\Override]
    public function findById(ResearchId $id): ?Research
    {
        $path = $this->getResearchPath($id->value);

        if (!$this->files->exists($path)) {
            return null;
        }

        try {
            return $this->loadResearchFromDirectory($path);
        } catch (\Throwable $e) {
            $this->logError('Failed to load research by ID', ['id' => $id->value, 'path' => $path], $e);
            return null;
        }
    }

    #[\Override]
    public function save(Research $research): void
    {
        $path = $this->getResearchPath($research->id);

        try {
            // Ensure research directory exists
            $this->ensureDirectory($path);

            // Create entry directories if they don't exist
            foreach ($research->entryDirs as $entryDir) {
                $entryDirPath = $this->files->normalizePath($path . '/' . $entryDir);
                $this->ensureDirectory($entryDirPath);
            }

            // Save research configuration
            $this->saveResearchConfig($path, $research);

            $this->logOperation('Saved research', [
                'id' => $research->id,
                'name' => $research->name,
                'path' => $path,
            ]);
        } catch (\Throwable $e) {
            $this->logError('Failed to save research', ['id' => $research->id], $e);
            throw $e;
        }
    }

    #[\Override]
    public function delete(ResearchId $id): bool
    {
        $path = $this->getResearchPath($id->value);

        if (!$this->files->exists($path)) {
            return false;
        }

        try {
            $deleted = $this->files->deleteDirectory($path);

            if ($deleted) {
                $this->logOperation('Deleted research', ['id' => $id->value, 'path' => $path]);
            }

            return $deleted;
        } catch (\Throwable $e) {
            $this->logError('Failed to delete research', ['id' => $id->value], $e);
            return false;
        }
    }

    #[\Override]
    public function exists(ResearchId $id): bool
    {
        $path = $this->getResearchPath($id->value);
        $configPath = $path . '/' . self::CONFIG_FILE;

        return $this->files->exists($configPath);
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
     * Load research from directory path
     */
    private function loadResearchFromDirectory(string $researchPath): ?Research
    {
        $configPath = $researchPath . '/' . self::CONFIG_FILE;

        if (!$this->files->exists($configPath)) {
            throw new \RuntimeException("Research configuration not found: {$configPath}");
        }

        $config = $this->readYamlFile($configPath);

        // Extract research ID from directory name
        $id = \basename($researchPath);

        return new Research(
            id: $id,
            name: $config['name'] ?? $id,
            description: $config['description'] ?? '',
            template: $config['template'] ?? '',
            status: $config['status'] ?? 'draft',
            tags: $config['tags'] ?? [],
            entryDirs: $config['entries']['dirs'] ?? [],
            memory: $config['memory'] ?? [],
            path: $researchPath,
        );
    }

    /**
     * Save research configuration to YAML file
     */
    private function saveResearchConfig(string $researchPath, Research $research): void
    {
        $configPath = $researchPath . '/' . self::CONFIG_FILE;

        $this->writeYamlFile($configPath, [
            'name' => $research->name,
            'description' => $research->description,
            'template' => $research->template,
            'status' => $research->status,
            'tags' => $research->tags,
            'memory' => $research->memory,
            'entries' => [
                'dirs' => $research->entryDirs,
            ],
        ]);
    }

    /**
     * Check if research matches the provided filters
     */
    private function matchesFilters(Research $research, array $filters): bool
    {
        // Status filter
        if (isset($filters['status']) && $research->status !== $filters['status']) {
            return false;
        }

        // Template filter
        if (isset($filters['template']) && $research->template !== $filters['template']) {
            return false;
        }

        // Tags filter (any of the provided tags should match)
        if (isset($filters['tags']) && \is_array($filters['tags'])) {
            $hasMatchingTag = false;
            foreach ($filters['tags'] as $filterTag) {
                if (\in_array($filterTag, $research->tags, true)) {
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
