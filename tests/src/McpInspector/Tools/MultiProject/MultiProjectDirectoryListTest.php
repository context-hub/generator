<?php

declare(strict_types=1);

namespace Tests\McpInspector\Tools\MultiProject;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\McpInspector\McpInspectorTestCase;

/**
 * Integration tests for directory-list tool with multi-project support.
 */
#[Group('mcp-inspector')]
final class MultiProjectDirectoryListTest extends McpInspectorTestCase
{
    use MultiProjectTestTrait;

    #[Test]
    public function it_lists_directory_from_project_a(): void
    {
        // Arrange
        $this->createFileInProject($this->projectADir, 'file1.txt', 'content1');
        $this->createFileInProject($this->projectADir, 'file2.txt', 'content2');

        // Act
        $result = $this->inspector->callTool('directory-list', [
            'path' => '.',
            'project' => 'project-a',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'file1.txt');
        $this->assertContentContains($result, 'file2.txt');
    }

    #[Test]
    public function it_lists_directory_from_project_b(): void
    {
        // Arrange
        $this->createFileInProject($this->projectBDir, 'alpha.txt', 'content');
        $this->createFileInProject($this->projectBDir, 'beta.txt', 'content');
        $this->createFileInProject($this->projectBDir, 'subdir/gamma.txt', 'content');

        // Act
        $result = $this->inspector->callTool('directory-list', [
            'path' => '.',
            'project' => 'project-b',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'alpha.txt');
        $this->assertContentContains($result, 'beta.txt');
    }

    #[Test]
    public function it_lists_different_files_from_different_projects(): void
    {
        // Arrange
        $this->createFileInProject($this->projectADir, 'project-a-only.txt', 'A content');
        $this->createFileInProject($this->projectBDir, 'project-b-only.txt', 'B content');

        // Act & Assert - Project A
        $resultA = $this->inspector->callTool('directory-list', ['path' => '.', 'project' => 'project-a']);
        $this->assertInspectorSuccess($resultA);
        $this->assertContentContains($resultA, 'project-a-only.txt');

        // Act & Assert - Project B
        $resultB = $this->inspector->callTool('directory-list', ['path' => '.', 'project' => 'project-b']);
        $this->assertInspectorSuccess($resultB);
        $this->assertContentContains($resultB, 'project-b-only.txt');
    }

    #[Test]
    public function it_lists_subdirectory_from_project_a(): void
    {
        // Arrange
        $this->createFileInProject($this->projectADir, 'src/app.php', '<?php');
        $this->createFileInProject($this->projectADir, 'src/config.php', '<?php');

        // Act
        $result = $this->inspector->callTool('directory-list', [
            'path' => 'src',
            'project' => 'project-a',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'app.php');
        $this->assertContentContains($result, 'config.php');
    }

    #[Test]
    public function it_fails_for_non_whitelisted_project(): void
    {
        // Act
        $result = $this->inspector->callTool('directory-list', [
            'path' => '.',
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
