<?php

declare(strict_types=1);

namespace Tests\McpInspector\Tools;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;
use Tests\McpInspector\McpInspectorTestCase;

/**
 * Tests for multi-project support in MCP tools.
 *
 * Tests the `project` parameter functionality for filesystem and git tools.
 */
#[Group('mcp-inspector')]
final class MultiProjectToolTest extends McpInspectorTestCase
{
    /** @var string[] Temporary directories to clean up */
    private array $tempDirectories = [];

    #[Test]
    public function file_read_with_invalid_project_returns_error(): void
    {
        // Arrange
        $this->createFile('test.txt', 'Hello, World!');

        // Act - Call with non-existent project
        $result = $this->inspector->callTool('file-read', [
            'path' => 'test.txt',
            'project' => 'non-existent-project',
        ]);

        // Assert
        $this->assertInspectorSuccess($result); // CLI succeeds
        $this->assertToolError($result);        // But tool returns error
        $this->assertContentContains($result, 'not available');
    }

    #[Test]
    public function file_write_with_invalid_project_returns_error(): void
    {
        // Act - Call with non-existent project
        $result = $this->inspector->callTool('file-write', [
            'path' => 'new-file.txt',
            'content' => 'Test content',
            'project' => 'invalid-project',
        ]);

        // Assert
        $this->assertInspectorSuccess($result); // CLI succeeds
        $this->assertToolError($result);        // But tool returns error
        $this->assertContentContains($result, 'not available');
    }

    #[Test]
    public function directory_list_with_invalid_project_returns_error(): void
    {
        // Act - Call with non-existent project
        $result = $this->inspector->callTool('directory-list', [
            'path' => '.',
            'project' => 'missing-project',
        ]);

        // Assert
        $this->assertInspectorSuccess($result); // CLI succeeds
        $this->assertToolError($result);        // But tool returns error
        $this->assertContentContains($result, 'not available');
    }

    #[Test]
    public function git_status_with_invalid_project_returns_error(): void
    {
        // Act - Call with non-existent project
        $result = $this->inspector->callTool('git-status', [
            'project' => 'unknown-project',
        ]);

        // Assert
        $this->assertInspectorSuccess($result); // CLI succeeds
        $this->assertToolError($result);        // But tool returns error
        $this->assertContentContains($result, 'not available');
    }

    #[Test]
    public function file_read_without_project_uses_current_project(): void
    {
        // Arrange
        $this->createFile('test.txt', 'Current project content');

        // Act - Call without project parameter (should use current project)
        $result = $this->inspector->callTool('file-read', [
            'path' => 'test.txt',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'Current project content');
    }

    #[Test]
    public function file_read_with_null_project_uses_current_project(): void
    {
        // Arrange
        $this->createFile('test.txt', 'Null project content');

        // Act - Call with explicit null project (should use current project)
        $result = $this->inspector->callTool('file-read', [
            'path' => 'test.txt',
            // Note: null project is handled by not passing the parameter
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'Null project content');
    }

    #[Test]
    public function error_message_suggests_projects_list(): void
    {
        // Arrange
        $this->createFile('test.txt', 'Hello');

        // Act
        $result = $this->inspector->callTool('file-read', [
            'path' => 'test.txt',
            'project' => 'bad-project',
        ]);

        // Assert
        $this->assertToolError($result);
        $this->assertContentContains($result, 'projects-list');
    }

    // ============================================================
    // Path-based project tests (issue-314)
    // ============================================================

    #[Test]
    public function file_read_works_with_path_based_project(): void
    {
        // Arrange - Create a sibling project directory
        $siblingProjectPath = $this->createSiblingProject('test-sibling');
        $this->createFileInProject($siblingProjectPath, 'data.txt', 'Sibling project data');

        // Create context.yaml with path-based project
        $this->createContextYamlWithPathProject('sibling', '../test-sibling');

        // Act
        $result = $this->inspector->callTool('file-read', [
            'path' => 'data.txt',
            'project' => 'sibling',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'Sibling project data');
    }

    #[Test]
    public function directory_list_works_with_path_based_project(): void
    {
        // Arrange - Create a sibling project with subdirectory
        $siblingPath = $this->createSiblingProject('dir-test-project');
        $this->createFileInProject($siblingPath, 'root-file.txt', 'Root content');
        $this->createFileInProject($siblingPath, 'subdir/nested.txt', 'Nested content');

        $this->createContextYamlWithPathProject('dir-project', '../dir-test-project');

        // Act
        $result = $this->inspector->callTool('directory-list', [
            'path' => '.',
            'project' => 'dir-project',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'root-file.txt');
        $this->assertContentContains($result, 'subdir');
    }

    #[Test]
    public function mixed_configuration_path_and_alias(): void
    {
        // Arrange - Path-based project
        $pathProjectDir = $this->createSiblingProject('path-project');
        $this->createFileInProject($pathProjectDir, 'path-file.txt', 'From path project');

        // Create context.yaml with path-based project only
        // (alias-based projects need global registration which is complex in tests)
        $this->createContextYaml([
            'projects' => [
                ['name' => 'local-proj', 'path' => '../path-project'],
            ],
        ]);

        // Act - Read from path-based project
        $result = $this->inspector->callTool('file-read', [
            'path' => 'path-file.txt',
            'project' => 'local-proj',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'From path project');
    }

    #[Test]
    public function invalid_path_project_is_skipped(): void
    {
        // Arrange - Create context.yaml with non-existent path
        $this->createContextYaml([
            'projects' => [
                ['name' => 'ghost', 'path' => '../non-existent-dir-12345'],
            ],
        ]);

        // Act - Try to use the invalid project
        $result = $this->inspector->callTool('file-read', [
            'path' => 'test.txt',
            'project' => 'ghost',
        ]);

        // Assert - Should fail with "not available" (project wasn't registered)
        $this->assertInspectorSuccess($result); // CLI succeeds
        $this->assertToolError($result);        // But tool returns error
        $this->assertContentContains($result, 'not available');
    }

    #[Test]
    public function path_based_project_with_description(): void
    {
        // Arrange
        $projectPath = $this->createSiblingProject('described-project');
        $this->createFileInProject($projectPath, 'info.txt', 'Project with description');

        $this->createContextYaml([
            'projects' => [
                [
                    'name' => 'described',
                    'description' => 'A project with a helpful description',
                    'path' => '../described-project',
                ],
            ],
        ]);

        // Act - Verify project works
        $result = $this->inspector->callTool('file-read', [
            'path' => 'info.txt',
            'project' => 'described',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'Project with description');
    }

    #[Test]
    public function multiple_path_based_projects(): void
    {
        // Arrange - Create two sibling projects
        $project1Path = $this->createSiblingProject('project-alpha');
        $this->createFileInProject($project1Path, 'marker.txt', 'ALPHA PROJECT');

        $project2Path = $this->createSiblingProject('project-beta');
        $this->createFileInProject($project2Path, 'marker.txt', 'BETA PROJECT');

        $this->createContextYaml([
            'projects' => [
                ['name' => 'alpha', 'path' => '../project-alpha'],
                ['name' => 'beta', 'path' => '../project-beta'],
            ],
        ]);

        // Act - Read from both projects
        $resultAlpha = $this->inspector->callTool('file-read', [
            'path' => 'marker.txt',
            'project' => 'alpha',
        ]);

        $resultBeta = $this->inspector->callTool('file-read', [
            'path' => 'marker.txt',
            'project' => 'beta',
        ]);

        // Assert
        $this->assertInspectorSuccess($resultAlpha);
        $this->assertContentContains($resultAlpha, 'ALPHA PROJECT');

        $this->assertInspectorSuccess($resultBeta);
        $this->assertContentContains($resultBeta, 'BETA PROJECT');
    }

    #[Test]
    public function path_based_project_with_nested_path(): void
    {
        // Arrange - Create a deeply nested project structure
        $parentDir = $this->createSiblingProject('parent');
        $nestedPath = $parentDir . '/level1/level2/nested-project';
        \mkdir($nestedPath, 0755, true);
        $this->createFileInProject($nestedPath, 'deep.txt', 'Deep nested content');

        // Reference through complex relative path
        $this->createContextYamlWithPathProject('nested', '../parent/level1/level2/nested-project');

        // Act
        $result = $this->inspector->callTool('file-read', [
            'path' => 'deep.txt',
            'project' => 'nested',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'Deep nested content');
    }

    #[\Override]
    protected function tearDown(): void
    {
        // Clean up sibling project directories
        foreach ($this->tempDirectories as $dir) {
            $this->removeDir($dir);
        }

        parent::tearDown();
    }

    // ============================================================
    // Helper methods
    // ============================================================

    /**
     * Create a sibling project directory (outside workDir but at same level).
     */
    private function createSiblingProject(string $name): string
    {
        $path = \dirname($this->workDir) . '/' . $name;

        if (!\is_dir($path)) {
            \mkdir($path, 0755, true);
        }

        // Track for cleanup
        $this->tempDirectories[] = $path;

        return $path;
    }

    /**
     * Create a file within a project directory.
     */
    private function createFileInProject(string $projectPath, string $relativePath, string $content): void
    {
        $filePath = $projectPath . '/' . $relativePath;
        $dir = \dirname($filePath);

        if (!\is_dir($dir)) {
            \mkdir($dir, 0755, true);
        }

        \file_put_contents($filePath, $content);
    }

    /**
     * Create context.yaml with a single path-based project.
     */
    private function createContextYamlWithPathProject(string $name, string $path, ?string $description = null): void
    {
        $project = ['name' => $name, 'path' => $path];

        if ($description !== null) {
            $project['description'] = $description;
        }

        $this->createContextYaml([
            'projects' => [$project],
        ]);
    }

    /**
     * Create or update context.yaml with given configuration.
     */
    private function createContextYaml(array $config): void
    {
        // Merge with minimal required config
        $fullConfig = \array_merge(['documents' => []], $config);

        $yaml = Yaml::dump($fullConfig, 4);
        $this->createFile('context.yaml', $yaml);
    }
}
