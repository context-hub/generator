<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Storage\FileStorage;

use Butschster\ContextGenerator\Drafling\Domain\Model\Project;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\ProjectId;
use Butschster\ContextGenerator\Drafling\Repository\ProjectRepositoryInterface;

/**
 * File-based project repository implementation
 */
final class FileProjectRepository extends FileStorageRepositoryBase implements ProjectRepositoryInterface
{
    #[\Override]
    public function findAll(array $filters = []): array
    {
        $projects = [];
        $projectPaths = $this->directoryScanner->scanProjects($this->getBasePath());

        foreach ($projectPaths as $projectPath) {
            try {
                $project = $this->loadProjectFromDirectory($projectPath);
                if ($project !== null && $this->matchesFilters($project, $filters)) {
                    $projects[] = $project;
                }
            } catch (\Throwable $e) {
                $this->logError('Failed to load project', ['path' => $projectPath], $e);
            }
        }

        $this->logOperation('Loaded projects', [
            'count' => \count($projects),
            'total_scanned' => \count($projectPaths),
        ]);

        return $projects;
    }

    #[\Override]
    public function findById(ProjectId $id): ?Project
    {
        $projectPath = $this->getProjectPath($id->value);

        if (!$this->files->exists($projectPath)) {
            return null;
        }

        try {
            return $this->loadProjectFromDirectory($projectPath);
        } catch (\Throwable $e) {
            $this->logError('Failed to load project by ID', ['id' => $id->value, 'path' => $projectPath], $e);
            return null;
        }
    }

    #[\Override]
    public function save(Project $project): void
    {
        $projectPath = $this->getProjectPath($project->id);

        try {
            // Ensure project directory exists
            $this->ensureDirectory($projectPath);

            // Create entry directories if they don't exist
            foreach ($project->entryDirs as $entryDir) {
                $entryDirPath = $this->files->normalizePath($projectPath . '/' . $entryDir);
                $this->ensureDirectory($entryDirPath);
            }

            // Save project configuration
            $this->saveProjectConfig($projectPath, $project);

            $this->logOperation('Saved project', [
                'id' => $project->id,
                'name' => $project->name,
                'path' => $projectPath,
            ]);
        } catch (\Throwable $e) {
            $this->logError('Failed to save project', ['id' => $project->id], $e);
            throw $e;
        }
    }

    #[\Override]
    public function delete(ProjectId $id): bool
    {
        $projectPath = $this->getProjectPath($id->value);

        if (!$this->files->exists($projectPath)) {
            return false;
        }

        try {
            $deleted = $this->files->deleteDirectory($projectPath);

            if ($deleted) {
                $this->logOperation('Deleted project', ['id' => $id->value, 'path' => $projectPath]);
            }

            return $deleted;
        } catch (\Throwable $e) {
            $this->logError('Failed to delete project', ['id' => $id->value], $e);
            return false;
        }
    }

    #[\Override]
    public function exists(ProjectId $id): bool
    {
        $projectPath = $this->getProjectPath($id->value);
        $configPath = $projectPath . '/project.yaml';

        return $this->files->exists($configPath);
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
     * Load project from directory path
     */
    private function loadProjectFromDirectory(string $projectPath): ?Project
    {
        $configPath = $projectPath . '/project.yaml';

        if (!$this->files->exists($configPath)) {
            throw new \RuntimeException("Project configuration not found: {$configPath}");
        }

        $config = $this->readYamlFile($configPath);

        if (!isset($config['project'])) {
            throw new \RuntimeException("Invalid project configuration: missing 'project' section");
        }

        $projectData = $config['project'];

        // Extract project ID from directory name
        $projectId = \basename($projectPath);

        return new Project(
            id: $projectId,
            name: $projectData['name'] ?? $projectId,
            description: $projectData['description'] ?? '',
            template: $projectData['template'] ?? '',
            status: $projectData['status'] ?? 'draft',
            tags: $projectData['tags'] ?? [],
            entryDirs: $projectData['entries']['dirs'] ?? [],
            memory: $projectData['memory'] ?? [],
            projectPath: $projectPath,
        );
    }

    /**
     * Save project configuration to YAML file
     */
    private function saveProjectConfig(string $projectPath, Project $project): void
    {
        $configPath = $projectPath . '/project.yaml';

        $config = [
            'project' => [
                'name' => $project->name,
                'description' => $project->description,
                'template' => $project->template,
                'status' => $project->status,
                'tags' => $project->tags,
                'memory' => $project->memory,
                'entries' => [
                    'dirs' => $project->entryDirs,
                ],
            ],
        ];

        $this->writeYamlFile($configPath, $config);
    }

    /**
     * Check if project matches the provided filters
     */
    private function matchesFilters(Project $project, array $filters): bool
    {
        // Status filter
        if (isset($filters['status']) && $project->status !== $filters['status']) {
            return false;
        }

        // Template filter
        if (isset($filters['template']) && $project->template !== $filters['template']) {
            return false;
        }

        // Tags filter (any of the provided tags should match)
        if (isset($filters['tags']) && \is_array($filters['tags'])) {
            $hasMatchingTag = false;
            foreach ($filters['tags'] as $filterTag) {
                if (\in_array($filterTag, $project->tags, true)) {
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
