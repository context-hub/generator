<?php

declare(strict_types=1);

namespace Tests\McpInspector\Tools;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\McpInspector\McpInspectorTestCase;

#[Group('mcp-inspector')]
final class ToolsListTest extends McpInspectorTestCase
{
    #[Test]
    public function it_lists_all_available_tools(): void
    {
        // Act
        $result = $this->inspector->listTools();

        // Assert
        $this->assertInspectorSuccess($result);

        $tools = $result->getTools();
        $this->assertNotEmpty($tools, 'Should have at least one tool');
    }

    #[Test]
    public function it_includes_file_read_tool(): void
    {
        // Act
        $result = $this->inspector->listTools();

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertOutputContains($result, 'file-read');
    }

    #[Test]
    public function it_includes_directory_list_tool(): void
    {
        // Act
        $result = $this->inspector->listTools();

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertOutputContains($result, 'directory-list');
    }

    #[Test]
    public function it_includes_git_status_tool(): void
    {
        // Act
        $result = $this->inspector->listTools();

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertOutputContains($result, 'git-status');
    }

    #[Test]
    public function tools_have_json_schema(): void
    {
        // Act
        $result = $this->inspector->listTools();

        // Assert
        $this->assertInspectorSuccess($result);

        $tools = $result->getTools();
        foreach ($tools as $tool) {
            $this->assertArrayHasKey('name', $tool, 'Tool should have name');
            $this->assertArrayHasKey('description', $tool, 'Tool should have description');
            $this->assertArrayHasKey('inputSchema', $tool, 'Tool should have inputSchema');
        }
    }
}
