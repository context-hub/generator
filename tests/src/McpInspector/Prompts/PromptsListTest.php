<?php

declare(strict_types=1);

namespace Tests\McpInspector\Prompts;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\McpInspector\McpInspectorTestCase;

#[Group('mcp-inspector')]
final class PromptsListTest extends McpInspectorTestCase
{
    #[Test]
    public function it_lists_all_available_prompts(): void
    {
        // Act
        $result = $this->inspector->listPrompts();

        // Assert
        $this->assertInspectorSuccess($result);

        $prompts = $result->getPrompts();
        $this->assertIsArray($prompts);
    }

    #[Test]
    public function prompts_have_required_fields(): void
    {
        // Act
        $result = $this->inspector->listPrompts();

        // Assert
        $this->assertInspectorSuccess($result);

        $prompts = $result->getPrompts();
        foreach ($prompts as $prompt) {
            $this->assertArrayHasKey('name', $prompt, 'Prompt should have name');
        }
    }

    #[Test]
    public function it_can_get_prompt_by_name(): void
    {
        // First get the list of prompts
        $listResult = $this->inspector->listPrompts();
        $this->assertInspectorSuccess($listResult);

        $prompts = $listResult->getPrompts();

        // Skip if no prompts available
        if (empty($prompts)) {
            $this->markTestSkipped('No prompts available to test');
        }

        // Try to get the first prompt
        $name = $prompts[0]['name'];
        $result = $this->inspector->getPrompt($name);

        // Assert
        $this->assertInspectorSuccess($result);
    }
}
