<?php

declare(strict_types=1);

namespace Tests\McpInspector\Tools\MultiProject;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\McpInspector\McpInspectorTestCase;

/**
 * Integration tests for git tools with multi-project support.
 */
#[Group('mcp-inspector')]
final class MultiProjectGitTest extends McpInspectorTestCase
{
    use MultiProjectTestTrait;

    #[Test]
    public function it_gets_git_status_from_project_a(): void
    {
        // Arrange
        $this->initGitRepo($this->projectADir);
        $this->createFileInProject($this->projectADir, 'untracked.txt', 'Untracked file');

        // Act
        $result = $this->inspector->callTool('git-status', [
            'project' => 'project-a',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'untracked.txt');
    }

    #[Test]
    public function it_gets_git_status_from_project_b(): void
    {
        // Arrange
        $this->initGitRepo($this->projectBDir);
        $this->createFileInProject($this->projectBDir, 'new-file.txt', 'New file content');

        // Act
        $result = $this->inspector->callTool('git-status', [
            'project' => 'project-b',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'new-file.txt');
    }

    #[Test]
    public function it_gets_git_status_from_different_projects(): void
    {
        // Arrange
        $this->initGitRepo($this->projectADir);
        $this->initGitRepo($this->projectBDir);
        $this->createFileInProject($this->projectADir, 'file-a.txt', 'File in A');
        $this->createFileInProject($this->projectBDir, 'file-b.txt', 'File in B');

        // Act & Assert - Project A
        $resultA = $this->inspector->callTool('git-status', ['project' => 'project-a']);
        $this->assertInspectorSuccess($resultA);
        $this->assertContentContains($resultA, 'file-a.txt');

        // Act & Assert - Project B
        $resultB = $this->inspector->callTool('git-status', ['project' => 'project-b']);
        $this->assertInspectorSuccess($resultB);
        $this->assertContentContains($resultB, 'file-b.txt');
    }

    #[Test]
    public function it_adds_file_to_git_in_project_a(): void
    {
        // Arrange
        $this->initGitRepo($this->projectADir);
        $this->createFileInProject($this->projectADir, 'to-add.txt', 'File to add');

        // Act
        $result = $this->inspector->callTool('git-add', [
            'paths' => ['to-add.txt'],
            'project' => 'project-a',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
    }

    #[Test]
    public function it_adds_file_to_git_in_project_b(): void
    {
        // Arrange
        $this->initGitRepo($this->projectBDir);
        $this->createFileInProject($this->projectBDir, 'staged.txt', 'File to stage');

        // Act
        $result = $this->inspector->callTool('git-add', [
            'paths' => ['staged.txt'],
            'project' => 'project-b',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
    }

    #[Test]
    public function git_status_fails_for_non_whitelisted_project(): void
    {
        // Act
        $result = $this->inspector->callTool('git-status', [
            'project' => 'non-whitelisted-project',
        ]);

        // Assert
        $this->assertInspectorSuccess($result); // CLI succeeds
        $this->assertToolError($result);        // But tool returns error
        $this->assertContentContains($result, 'not available');
    }

    #[Test]
    public function git_add_fails_for_non_whitelisted_project(): void
    {
        // Arrange
        $this->createFile('test.txt', 'content');

        // Act
        $result = $this->inspector->callTool('git-add', [
            'paths' => ['test.txt'],
            'project' => 'non-whitelisted-project',
        ]);

        // Assert
        $this->assertInspectorSuccess($result); // CLI succeeds
        $this->assertToolError($result);        // But tool returns error
        $this->assertContentContains($result, 'not available');
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMultiProject();
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->tearDownMultiProject();
        parent::tearDown();
    }
}
