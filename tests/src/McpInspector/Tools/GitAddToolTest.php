<?php

declare(strict_types=1);

namespace Tests\McpInspector\Tools;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\McpInspector\McpInspectorTestCase;

#[Group('mcp-inspector')]
final class GitAddToolTest extends McpInspectorTestCase
{
    #[Test]
    public function it_stages_a_single_file(): void
    {
        // Arrange
        $this->createFile('test.txt', 'content');

        // Act
        $result = $this->inspector->callTool('git-add', [
            'paths' => ['test.txt'],
        ]);

        // Assert
        $this->assertInspectorSuccess($result);

        // Verify file is staged
        $output = [];
        \exec("cd {$this->workDir} && git status --porcelain", $output);
        $status = \implode("\n", $output);
        $this->assertStringContainsString('test.txt', $status);
    }

    #[Test]
    public function it_stages_multiple_files(): void
    {
        // Arrange
        $this->createFile('file1.txt', 'content 1');
        $this->createFile('file2.txt', 'content 2');

        // Act
        $result = $this->inspector->callTool('git-add', [
            'paths' => ['file1.txt', 'file2.txt'],
        ]);

        // Assert
        $this->assertInspectorSuccess($result);

        // Verify files are staged
        $output = [];
        \exec("cd {$this->workDir} && git status --porcelain", $output);
        $status = \implode("\n", $output);
        $this->assertStringContainsString('file1.txt', $status);
        $this->assertStringContainsString('file2.txt', $status);
    }

    #[Test]
    public function it_stages_all_files_with_dot(): void
    {
        // Arrange
        $this->createFile('file1.txt', 'content 1');
        $this->createFile('subdir/file2.txt', 'content 2');

        // Act
        $result = $this->inspector->callTool('git-add', [
            'paths' => ['.'],
        ]);

        // Assert
        $this->assertInspectorSuccess($result);

        // Verify all files are staged
        $output = [];
        \exec("cd {$this->workDir} && git status --porcelain", $output);
        $status = \implode("\n", $output);
        $this->assertStringContainsString('file1.txt', $status);
        $this->assertStringContainsString('file2.txt', $status);
    }

    #[Test]
    public function it_stages_modified_file(): void
    {
        // Arrange - create, commit, then modify
        $this->createFile('tracked.txt', 'original');
        \exec("cd {$this->workDir} && git add tracked.txt && git commit -q -m 'Initial'");
        $this->createFile('tracked.txt', 'modified');

        // Act
        $result = $this->inspector->callTool('git-add', [
            'paths' => ['tracked.txt'],
        ]);

        // Assert
        $this->assertInspectorSuccess($result);

        // Verify file is staged
        $output = [];
        \exec("cd {$this->workDir} && git status --porcelain", $output);
        $status = \implode("\n", $output);
        $this->assertStringContainsString('tracked.txt', $status);
    }

    #[Test]
    public function it_stages_files_in_subdirectory(): void
    {
        // Arrange
        $this->createFile('src/App.php', '<?php');
        $this->createFile('src/Kernel.php', '<?php');

        // Act
        $result = $this->inspector->callTool('git-add', [
            'paths' => ['src'],
        ]);

        // Assert
        $this->assertInspectorSuccess($result);

        // Verify files are staged
        $output = [];
        \exec("cd {$this->workDir} && git status --porcelain", $output);
        $status = \implode("\n", $output);
        $this->assertStringContainsString('App.php', $status);
        $this->assertStringContainsString('Kernel.php', $status);
    }

    #[Test]
    public function it_fails_for_non_existent_file(): void
    {
        // Act
        $result = $this->inspector->callTool('git-add', [
            'paths' => ['non-existent.txt'],
        ]);

        // Assert
        $this->assertToolError($result);
    }

    #[Test]
    public function it_stages_default_when_no_paths_provided(): void
    {
        // git-add defaults to ['.'] when no paths provided
        // Act
        $result = $this->inspector->callTool('git-add', []);

        // Assert - should succeed with default paths
        $this->assertInspectorSuccess($result);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Initialize git repo in work directory
        \exec("cd {$this->workDir} && git init -q");
        \exec("cd {$this->workDir} && git config user.email 'test@test.com'");
        \exec("cd {$this->workDir} && git config user.name 'Test'");
    }
}
