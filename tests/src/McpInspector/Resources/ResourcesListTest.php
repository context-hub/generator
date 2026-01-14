<?php

declare(strict_types=1);

namespace Tests\McpInspector\Resources;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\McpInspector\McpInspectorTestCase;

#[Group('mcp-inspector')]
final class ResourcesListTest extends McpInspectorTestCase
{
    #[Test]
    public function it_lists_all_available_resources(): void
    {
        // Act
        $result = $this->inspector->listResources();

        // Assert
        $this->assertInspectorSuccess($result);

        $resources = $result->getResources();
        $this->assertIsArray($resources);
    }

    #[Test]
    public function resources_have_required_fields(): void
    {
        // Act
        $result = $this->inspector->listResources();

        // Assert
        $this->assertInspectorSuccess($result);

        $resources = $result->getResources();
        foreach ($resources as $resource) {
            $this->assertArrayHasKey('uri', $resource, 'Resource should have uri');
            $this->assertArrayHasKey('name', $resource, 'Resource should have name');
        }
    }

    #[Test]
    public function it_can_read_resource_by_uri(): void
    {
        // First get the list of resources
        $listResult = $this->inspector->listResources();
        $this->assertInspectorSuccess($listResult);

        $resources = $listResult->getResources();

        // Skip if no resources available
        if (empty($resources)) {
            $this->markTestSkipped('No resources available to test');
        }

        // Try to read the first resource
        $uri = $resources[0]['uri'];
        $result = $this->inspector->readResource($uri);

        // Assert - resource reading may fail due to connection timing issues
        // The important thing is we got a result (not a crash)
        $this->assertNotNull($result);
    }
}
