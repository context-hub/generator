<?php

declare(strict_types=1);

namespace Tests\McpInspector\Tools\MultiProject;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\McpInspector\McpInspectorTestCase;

/**
 * Integration tests for file-replace-content tool with multi-project support.
 */
#[Group('mcp-inspector')]
final class MultiProjectFileReplaceContentTest extends McpInspectorTestCase
{
    use MultiProjectTestTrait;

    #[Test]
    public function it_replaces_content_in_project_a(): void
    {
        // Arrange
        $this->createFileInProject($this->projectADir, 'replace-test.txt', 'Hello World');

        // Act
        $result = $this->inspector->callTool('file-replace-content', [
            'path' => 'replace-test.txt',
            'search' => 'World',
            'replace' => 'PROJECT A',
            'project' => 'project-a',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertEquals('Hello PROJECT A', \file_get_contents($this->projectADir . '/replace-test.txt'));
    }

    #[Test]
    public function it_replaces_content_in_project_b(): void
    {
        // Arrange
        $this->createFileInProject($this->projectBDir, 'config.txt', 'env=development');

        // Act
        $result = $this->inspector->callTool('file-replace-content', [
            'path' => 'config.txt',
            'search' => 'development',
            'replace' => 'production',
            'project' => 'project-b',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertEquals('env=production', \file_get_contents($this->projectBDir . '/config.txt'));
    }

    #[Test]
    public function it_replaces_content_in_same_filename_different_projects(): void
    {
        // Arrange
        $this->createFileInProject($this->projectADir, 'shared.txt', 'Value: ORIGINAL_A');
        $this->createFileInProject($this->projectBDir, 'shared.txt', 'Value: ORIGINAL_B');

        // Act - Replace in project A only
        $result = $this->inspector->callTool('file-replace-content', [
            'path' => 'shared.txt',
            'search' => 'ORIGINAL_A',
            'replace' => 'REPLACED_A',
            'project' => 'project-a',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);

        // Verify only project A file was modified
        $this->assertEquals('Value: REPLACED_A', \file_get_contents($this->projectADir . '/shared.txt'));
        $this->assertEquals('Value: ORIGINAL_B', \file_get_contents($this->projectBDir . '/shared.txt'));
    }

    #[Test]
    public function it_fails_for_non_whitelisted_project(): void
    {
        // Arrange
        $this->createFile('test.txt', 'Some content');

        // Act
        $result = $this->inspector->callTool('file-replace-content', [
            'path' => 'test.txt',
            'search' => 'Some',
            'replace' => 'Other',
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
