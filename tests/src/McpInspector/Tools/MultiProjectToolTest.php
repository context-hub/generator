<?php

declare(strict_types=1);

namespace Tests\McpInspector\Tools;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\McpInspector\McpInspectorTestCase;

/**
 * Tests for multi-project support in MCP tools.
 *
 * Tests the `project` parameter functionality for filesystem and git tools.
 */
#[Group('mcp-inspector')]
final class MultiProjectToolTest extends McpInspectorTestCase
{
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
}
