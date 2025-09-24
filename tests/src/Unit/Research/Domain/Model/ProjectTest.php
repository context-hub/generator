<?php

declare(strict_types=1);

namespace Tests\Unit\Research\Domain\Model;

use Butschster\ContextGenerator\Research\Domain\Model\Research;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Project domain model
 */
final class ProjectTest extends TestCase
{
    public function testProjectConstruction(): void
    {
        $project = new Research(
            id: 'proj_123',
            name: 'Test Project',
            description: 'A test project',
            template: 'blog',
            status: 'active',
            tags: ['web', 'blog'],
            entryDirs: ['posts', 'pages'],
            memory: ['Initial memory entry', 'Another memory'],
            path: '/path/to/project',
        );

        $this->assertSame('proj_123', $project->id);
        $this->assertSame('Test Project', $project->name);
        $this->assertSame('A test project', $project->description);
        $this->assertSame('blog', $project->template);
        $this->assertSame('active', $project->status);
        $this->assertSame(['web', 'blog'], $project->tags);
        $this->assertSame(['posts', 'pages'], $project->entryDirs);
        $this->assertSame(['Initial memory entry', 'Another memory'], $project->memory);
        $this->assertSame('/path/to/project', $project->path);
    }

    public function testProjectConstructionWithDefaults(): void
    {
        $project = new Research(
            id: 'proj_456',
            name: 'Minimal Project',
            description: 'Basic project',
            template: 'simple',
            status: 'draft',
            tags: [],
            entryDirs: [],
        );

        $this->assertSame('proj_456', $project->id);
        $this->assertSame('Minimal Project', $project->name);
        $this->assertSame([], $project->memory); // Should default to empty array
        $this->assertNull($project->path); // Should default to null
    }

    public function testWithUpdates(): void
    {
        $original = new Research(
            id: 'proj_789',
            name: 'Original Name',
            description: 'Original description',
            template: 'blog',
            status: 'draft',
            tags: ['old'],
            entryDirs: ['old-dir'],
            memory: ['old memory'],
        );

        $updated = $original->withUpdates(
            name: 'Updated Name',
            description: 'Updated description',
            status: 'active',
            tags: ['new', 'updated'],
            entryDirs: ['new-dir', 'another-dir'],
            memory: ['new memory', 'updated memory'],
        );

        // Original should be unchanged
        $this->assertSame('Original Name', $original->name);
        $this->assertSame('Original description', $original->description);
        $this->assertSame('draft', $original->status);
        $this->assertSame(['old'], $original->tags);
        $this->assertSame(['old-dir'], $original->entryDirs);
        $this->assertSame(['old memory'], $original->memory);

        // New instance should have updated values
        $this->assertSame('proj_789', $updated->id); // ID unchanged
        $this->assertSame('Updated Name', $updated->name);
        $this->assertSame('Updated description', $updated->description);
        $this->assertSame('active', $updated->status);
        $this->assertSame(['new', 'updated'], $updated->tags);
        $this->assertSame(['new-dir', 'another-dir'], $updated->entryDirs);
        $this->assertSame(['new memory', 'updated memory'], $updated->memory);
        $this->assertSame('blog', $updated->template); // Template unchanged
    }

    public function testWithUpdatesPartial(): void
    {
        $original = new Research(
            id: 'proj_abc',
            name: 'Test',
            description: 'Description',
            template: 'blog',
            status: 'draft',
            tags: ['tag1'],
            entryDirs: ['dir1'],
            memory: ['mem1'],
        );

        $updated = $original->withUpdates(name: 'New Name');

        $this->assertSame('New Name', $updated->name);
        $this->assertSame('Description', $updated->description); // Unchanged
        $this->assertSame('draft', $updated->status); // Unchanged
        $this->assertSame(['tag1'], $updated->tags); // Unchanged
        $this->assertSame(['dir1'], $updated->entryDirs); // Unchanged
        $this->assertSame(['mem1'], $updated->memory); // Unchanged
    }

    public function testWithAddedMemory(): void
    {
        $project = new Research(
            id: 'proj_mem',
            name: 'Memory Test',
            description: 'Testing memory',
            template: 'test',
            status: 'active',
            tags: [],
            entryDirs: [],
            memory: ['First memory', 'Second memory'],
        );

        $updated = $project->withAddedMemory('Third memory');

        // Original unchanged
        $this->assertSame(['First memory', 'Second memory'], $project->memory);

        // New instance has added memory
        $this->assertSame(['First memory', 'Second memory', 'Third memory'], $updated->memory);

        // Other properties unchanged
        $this->assertSame('proj_mem', $updated->id);
        $this->assertSame('Memory Test', $updated->name);
    }

    public function testWithAddedMemoryToEmptyArray(): void
    {
        $project = new Research(
            id: 'proj_empty',
            name: 'Empty Memory',
            description: 'No memory yet',
            template: 'test',
            status: 'active',
            tags: [],
            entryDirs: [],
            memory: [],
        );

        $updated = $project->withAddedMemory('First memory entry');

        $this->assertSame([], $project->memory);
        $this->assertSame(['First memory entry'], $updated->memory);
    }

    public function testJsonSerialize(): void
    {
        $project = new Research(
            id: 'proj_json',
            name: 'JSON Test',
            description: 'Testing JSON serialization',
            template: 'api',
            status: 'published',
            tags: ['api', 'json'],
            entryDirs: ['endpoints'],
            memory: ['json memory'],
        );

        $serialized = $project->jsonSerialize();

        $this->assertSame('JSON Test', $serialized['title']);
        $this->assertSame('published', $serialized['status']);
        $this->assertSame('api', $serialized['research_type']);

        // Check metadata
        $this->assertSame('Testing JSON serialization', $serialized['metadata']['description']);
        $this->assertSame(['api', 'json'], $serialized['metadata']['tags']);
        $this->assertSame(['json memory'], $serialized['metadata']['memory']);
    }

    public function testProjectIsImmutable(): void
    {
        $project = new Research(
            id: 'proj_immutable',
            name: 'Immutable Test',
            description: 'Testing immutability',
            template: 'test',
            status: 'active',
            tags: ['immutable'],
            entryDirs: ['test'],
            memory: ['original'],
        );

        $updated = $project->withUpdates(name: 'Updated');
        $withMemory = $project->withAddedMemory('new memory');

        // Original should be completely unchanged
        $this->assertSame('Immutable Test', $project->name);
        $this->assertSame(['original'], $project->memory);

        // Each method should return a new instance
        $this->assertNotSame($project, $updated);
        $this->assertNotSame($project, $withMemory);
        $this->assertNotSame($updated, $withMemory);
    }
}
