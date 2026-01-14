<?php

declare(strict_types=1);

namespace Tests\McpInspector\Tools\MultiProject;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\McpInspector\McpInspectorTestCase;

/**
 * Integration tests for file-read tool with multi-project support.
 */
#[Group('mcp-inspector')]
final class MultiProjectFileReadTest extends McpInspectorTestCase
{
    use MultiProjectTestTrait;

    #[Test]
    public function it_reads_file_from_current_project(): void
    {
        // Arrange
        $this->createFile('current.txt', 'Content from CURRENT project');

        // Act
        $result = $this->inspector->callTool('file-read', [
            'path' => 'current.txt',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'Content from CURRENT project');
    }

    #[Test]
    public function it_reads_file_from_project_a(): void
    {
        // Arrange
        $this->createFileInProject($this->projectADir, 'readme.txt', 'Content from PROJECT A');

        // Act
        $result = $this->inspector->callTool('file-read', [
            'path' => 'readme.txt',
            'project' => 'project-a',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'Content from PROJECT A');
    }

    #[Test]
    public function it_reads_file_from_project_b(): void
    {
        // Arrange
        $this->createFileInProject($this->projectBDir, 'data.txt', 'Content from PROJECT B');

        // Act
        $result = $this->inspector->callTool('file-read', [
            'path' => 'data.txt',
            'project' => 'project-b',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'Content from PROJECT B');
    }

    #[Test]
    public function it_reads_different_files_from_all_three_projects(): void
    {
        // Arrange
        $this->createFile('main.txt', 'MAIN PROJECT CONTENT');
        $this->createFileInProject($this->projectADir, 'alpha.txt', 'ALPHA PROJECT CONTENT');
        $this->createFileInProject($this->projectBDir, 'beta.txt', 'BETA PROJECT CONTENT');

        // Act & Assert - Current project
        $resultMain = $this->inspector->callTool('file-read', ['path' => 'main.txt']);
        $this->assertInspectorSuccess($resultMain);
        $this->assertContentContains($resultMain, 'MAIN PROJECT CONTENT');

        // Act & Assert - Project A
        $resultA = $this->inspector->callTool('file-read', ['path' => 'alpha.txt', 'project' => 'project-a']);
        $this->assertInspectorSuccess($resultA);
        $this->assertContentContains($resultA, 'ALPHA PROJECT CONTENT');

        // Act & Assert - Project B
        $resultB = $this->inspector->callTool('file-read', ['path' => 'beta.txt', 'project' => 'project-b']);
        $this->assertInspectorSuccess($resultB);
        $this->assertContentContains($resultB, 'BETA PROJECT CONTENT');
    }

    #[Test]
    public function it_reads_same_filename_from_different_projects(): void
    {
        // Arrange
        $this->createFile('config.txt', 'CONFIG: main-project-settings');
        $this->createFileInProject($this->projectADir, 'config.txt', 'CONFIG: project-a-settings');
        $this->createFileInProject($this->projectBDir, 'config.txt', 'CONFIG: project-b-settings');

        // Act & Assert - Current
        $resultMain = $this->inspector->callTool('file-read', ['path' => 'config.txt']);
        $this->assertInspectorSuccess($resultMain);
        $this->assertContentContains($resultMain, 'main-project-settings');

        // Act & Assert - Project A
        $resultA = $this->inspector->callTool('file-read', ['path' => 'config.txt', 'project' => 'project-a']);
        $this->assertInspectorSuccess($resultA);
        $this->assertContentContains($resultA, 'project-a-settings');

        // Act & Assert - Project B
        $resultB = $this->inspector->callTool('file-read', ['path' => 'config.txt', 'project' => 'project-b']);
        $this->assertInspectorSuccess($resultB);
        $this->assertContentContains($resultB, 'project-b-settings');
    }

    #[Test]
    public function it_fails_for_non_whitelisted_project(): void
    {
        // Arrange
        $this->createFile('test.txt', 'Some content');

        // Act
        $result = $this->inspector->callTool('file-read', [
            'path' => 'test.txt',
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
