<?php

declare(strict_types=1);

namespace Tests\McpInspector\Tools;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\McpInspector\McpInspectorTestCase;

#[Group('mcp-inspector')]
final class GitStatusToolTest extends McpInspectorTestCase
{
    #[Test]
    public function it_shows_git_status(): void
    {
        // Act
        $result = $this->inspector->callTool('git-status');

        // Assert
        $this->assertInspectorSuccess($result);
    }

    #[Test]
    public function it_shows_untracked_files(): void
    {
        // Arrange
        $this->createFile('untracked.txt', 'new content');

        // Act
        $result = $this->inspector->callTool('git-status');

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'untracked.txt');
    }

    #[Test]
    public function it_shows_modified_files(): void
    {
        // Arrange - create and commit a file, then modify it
        $this->createFile('tracked.txt', 'original');
        \exec("cd {$this->workDir} && git add tracked.txt && git commit -q -m 'Initial'");
        $this->createFile('tracked.txt', 'modified');

        // Act
        $result = $this->inspector->callTool('git-status');

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'tracked.txt');
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
