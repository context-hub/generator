<?php

declare(strict_types=1);

namespace Tests\McpInspector\Tools\MultiProject;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\McpInspector\McpInspectorTestCase;

/**
 * Integration tests for projects-list tool with multi-project support.
 */
#[Group('mcp-inspector')]
final class MultiProjectProjectsListTest extends McpInspectorTestCase
{
    use MultiProjectTestTrait;

    #[Test]
    public function it_shows_whitelisted_projects(): void
    {
        // Act
        $result = $this->inspector->callTool('projects-list', []);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'projects');
        $this->assertContentContains($result, 'project-a');
        $this->assertContentContains($result, 'project-b');
    }

    #[Test]
    public function it_shows_project_descriptions(): void
    {
        // Act
        $result = $this->inspector->callTool('projects-list', []);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'Test project A');
        $this->assertContentContains($result, 'Test project B');
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
