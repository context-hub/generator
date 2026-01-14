<?php

declare(strict_types=1);

namespace Tests\McpInspector\Tools\MultiProject;

use Butschster\ContextGenerator\McpServer\Projects\DTO\ProjectDTO;
use Butschster\ContextGenerator\McpServer\Projects\DTO\ProjectStateDTO;

/**
 * Shared setup for multi-project tests.
 */
trait MultiProjectTestTrait
{
    protected string $projectADir;
    protected string $projectBDir;
    protected string $stateDir;

    protected function setUpMultiProject(): void
    {
        // Create project A directory
        $this->projectADir = $this->createProjectDir('project-a');

        // Create project B directory
        $this->projectBDir = $this->createProjectDir('project-b');

        // Create state directory with .project-state.json
        $this->stateDir = $this->workDir;
        $this->createProjectStateFile();

        // Create context.yaml with whitelisted projects
        $this->createContextYamlWithProjects();

        // Re-create inspector with stateDir
        $this->inspector = $this->createInspector($this->workDir, $this->stateDir);
    }

    protected function tearDownMultiProject(): void
    {
        // Clean up project directories
        if (isset($this->projectADir) && \is_dir($this->projectADir)) {
            $this->removeDir($this->projectADir);
        }
        if (isset($this->projectBDir) && \is_dir($this->projectBDir)) {
            $this->removeDir($this->projectBDir);
        }
    }

    /**
     * Create a project directory.
     */
    protected function createProjectDir(string $name): string
    {
        $dir = \sys_get_temp_dir() . '/mcp-test-' . $name . '-' . \uniqid();
        \mkdir($dir, 0777, true);

        return $dir;
    }

    /**
     * Create a file in a specific project directory.
     */
    protected function createFileInProject(string $projectDir, string $relativePath, string $content): string
    {
        $fullPath = $projectDir . '/' . $relativePath;
        $dir = \dirname($fullPath);

        if (!\is_dir($dir)) {
            \mkdir($dir, 0777, true);
        }

        \file_put_contents($fullPath, $content);

        return $fullPath;
    }

    /**
     * Create .project-state.json with project aliases using DTOs.
     */
    protected function createProjectStateFile(): void
    {
        $state = new ProjectStateDTO(
            currentProject: null,
            projects: [
                $this->projectADir => new ProjectDTO(
                    path: $this->projectADir,
                    addedAt: \date('Y-m-d H:i:s'),
                ),
                $this->projectBDir => new ProjectDTO(
                    path: $this->projectBDir,
                    addedAt: \date('Y-m-d H:i:s'),
                ),
            ],
            aliases: [
                'project-a' => $this->projectADir,
                'project-b' => $this->projectBDir,
            ],
        );

        \file_put_contents(
            $this->stateDir . '/.project-state.json',
            \json_encode($state, JSON_PRETTY_PRINT),
        );
    }

    /**
     * Create context.yaml with whitelisted projects.
     */
    protected function createContextYamlWithProjects(): void
    {
        $yaml = <<<YAML
documents: []

projects:
  - name: project-a
    description: "Test project A"
  - name: project-b
    description: "Test project B"
YAML;

        \file_put_contents($this->workDir . '/context.yaml', $yaml);
    }

    /**
     * Initialize a git repository in the given directory.
     */
    protected function initGitRepo(string $dir): void
    {
        $cwd = \getcwd();
        \chdir($dir);
        \exec('git init 2>&1');
        \exec('git config user.email "test@example.com" 2>&1');
        \exec('git config user.name "Test User" 2>&1');
        \chdir($cwd);
    }
}
