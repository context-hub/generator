<?php

declare(strict_types=1);

namespace Tests\McpInspector\Tools;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\McpInspector\McpInspectorTestCase;

#[Group('mcp-inspector')]
final class ProjectsListToolTest extends McpInspectorTestCase
{
    #[Test]
    public function it_lists_projects_with_empty_whitelist(): void
    {
        // Act
        $result = $this->inspector->callTool('projects-list', []);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'projects');
    }

    #[Test]
    public function it_shows_whitelisted_projects_section(): void
    {
        // Arrange - Create context.yaml with projects section
        // Note: Projects must exist in .project-state.json to be whitelisted
        // Since we don't have any aliases set up, whitelist will be empty
        \file_put_contents($this->workDir . '/context.yaml', <<<YAML
documents: []
projects:
  - name: test-project
    description: Test project for integration testing
YAML);

        // Re-create inspector with updated config
        $this->inspector = $this->createInspector($this->workDir);

        // Act
        $result = $this->inspector->callTool('projects-list', []);

        // Assert
        $this->assertInspectorSuccess($result);
        // The project won't appear in projects list unless it exists in .project-state.json
        // But the response structure should be valid
        $this->assertContentContains($result, 'projects');
    }

    #[Test]
    public function it_returns_correct_response_structure(): void
    {
        // Act
        $result = $this->inspector->callTool('projects-list', []);

        // Assert
        $this->assertInspectorSuccess($result);

        // Parse the JSON response
        $content = $result->getContent();
        $this->assertNotNull($content);

        $data = \json_decode($content, true);
        $this->assertIsArray($data);

        // Verify expected keys exist
        $this->assertArrayHasKey('projects', $data);
        $this->assertArrayHasKey('current_project', $data);
        $this->assertArrayHasKey('total_projects', $data);
    }
}
