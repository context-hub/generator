<?php

declare(strict_types=1);

namespace Tests\McpInspector\Tools\MultiProject;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\McpInspector\McpInspectorTestCase;

/**
 * Integration tests for file-write tool with multi-project support.
 */
#[Group('mcp-inspector')]
final class MultiProjectFileWriteTest extends McpInspectorTestCase
{
    use MultiProjectTestTrait;

    #[Test]
    public function it_writes_file_to_project_a(): void
    {
        // Act
        $result = $this->inspector->callTool('file-write', [
            'path' => 'new-file.txt',
            'content' => 'Written to PROJECT A',
            'project' => 'project-a',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertFileExists($this->projectADir . '/new-file.txt');
        $this->assertEquals('Written to PROJECT A', \file_get_contents($this->projectADir . '/new-file.txt'));
    }

    #[Test]
    public function it_writes_file_to_project_b(): void
    {
        // Act
        $result = $this->inspector->callTool('file-write', [
            'path' => 'data.txt',
            'content' => 'Written to PROJECT B',
            'project' => 'project-b',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertFileExists($this->projectBDir . '/data.txt');
        $this->assertEquals('Written to PROJECT B', \file_get_contents($this->projectBDir . '/data.txt'));
    }

    #[Test]
    public function it_writes_same_filename_to_different_projects(): void
    {
        // Act - Write to project A
        $resultA = $this->inspector->callTool('file-write', [
            'path' => 'shared.txt',
            'content' => 'Content for A',
            'project' => 'project-a',
        ]);

        // Act - Write to project B
        $resultB = $this->inspector->callTool('file-write', [
            'path' => 'shared.txt',
            'content' => 'Content for B',
            'project' => 'project-b',
        ]);

        // Assert - Both writes succeeded
        $this->assertInspectorSuccess($resultA);
        $this->assertInspectorSuccess($resultB);

        // Assert - Files are in correct locations with correct content
        $this->assertEquals('Content for A', \file_get_contents($this->projectADir . '/shared.txt'));
        $this->assertEquals('Content for B', \file_get_contents($this->projectBDir . '/shared.txt'));
    }

    #[Test]
    public function it_writes_nested_file_to_project_a(): void
    {
        // Act
        $result = $this->inspector->callTool('file-write', [
            'path' => 'src/config/settings.txt',
            'content' => 'env=project-a',
            'project' => 'project-a',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertFileExists($this->projectADir . '/src/config/settings.txt');
        $this->assertEquals('env=project-a', \file_get_contents($this->projectADir . '/src/config/settings.txt'));
    }

    #[Test]
    public function it_fails_for_non_whitelisted_project(): void
    {
        // Act
        $result = $this->inspector->callTool('file-write', [
            'path' => 'test.txt',
            'content' => 'Test content',
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
