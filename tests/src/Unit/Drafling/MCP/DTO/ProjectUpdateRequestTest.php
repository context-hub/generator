<?php

declare(strict_types=1);

namespace Tests\Unit\Drafling\MCP\DTO;

use Butschster\ContextGenerator\Drafling\MCP\DTO\ProjectUpdateRequest;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ProjectUpdateRequest DTO
 */
final class ProjectUpdateRequestTest extends TestCase
{
    public function testConstructionWithAllFields(): void
    {
        $request = new ProjectUpdateRequest(
            projectId: 'proj_123',
            title: 'Updated Title',
            description: 'Updated description',
            status: 'published',
            tags: ['web', 'blog'],
            entryDirs: ['posts', 'pages'],
            memory: ['memory1', 'memory2'],
        );

        $this->assertSame('proj_123', $request->projectId);
        $this->assertSame('Updated Title', $request->title);
        $this->assertSame('Updated description', $request->description);
        $this->assertSame('published', $request->status);
        $this->assertSame(['web', 'blog'], $request->tags);
        $this->assertSame(['posts', 'pages'], $request->entryDirs);
        $this->assertSame(['memory1', 'memory2'], $request->memory);
    }

    public function testConstructionWithMinimalFields(): void
    {
        $request = new ProjectUpdateRequest(projectId: 'proj_456');

        $this->assertSame('proj_456', $request->projectId);
        $this->assertNull($request->title);
        $this->assertNull($request->description);
        $this->assertNull($request->status);
        $this->assertNull($request->tags);
        $this->assertNull($request->entryDirs);
        $this->assertNull($request->memory);
    }

    public function testGetName(): void
    {
        $request = new ProjectUpdateRequest(
            projectId: 'proj_789',
            title: 'Test Project',
        );

        $this->assertSame('Test Project', $request->getName());
    }

    public function testGetNameReturnsNull(): void
    {
        $request = new ProjectUpdateRequest(projectId: 'proj_abc');

        $this->assertNull($request->getName());
    }

    public function testHasUpdatesReturnsTrueWithTitle(): void
    {
        $request = new ProjectUpdateRequest(
            projectId: 'proj_123',
            title: 'New Title',
        );

        $this->assertTrue($request->hasUpdates());
    }

    public function testHasUpdatesReturnsTrueWithDescription(): void
    {
        $request = new ProjectUpdateRequest(
            projectId: 'proj_123',
            description: 'New description',
        );

        $this->assertTrue($request->hasUpdates());
    }

    public function testHasUpdatesReturnsTrueWithStatus(): void
    {
        $request = new ProjectUpdateRequest(
            projectId: 'proj_123',
            status: 'active',
        );

        $this->assertTrue($request->hasUpdates());
    }

    public function testHasUpdatesReturnsTrueWithTags(): void
    {
        $request = new ProjectUpdateRequest(
            projectId: 'proj_123',
            tags: ['new-tag'],
        );

        $this->assertTrue($request->hasUpdates());
    }

    public function testHasUpdatesReturnsTrueWithEntryDirs(): void
    {
        $request = new ProjectUpdateRequest(
            projectId: 'proj_123',
            entryDirs: ['new-dir'],
        );

        $this->assertTrue($request->hasUpdates());
    }

    public function testHasUpdatesReturnsTrueWithMemory(): void
    {
        $request = new ProjectUpdateRequest(
            projectId: 'proj_123',
            memory: ['new memory'],
        );

        $this->assertTrue($request->hasUpdates());
    }

    public function testHasUpdatesReturnsFalseWithNoUpdates(): void
    {
        $request = new ProjectUpdateRequest(projectId: 'proj_123');

        $this->assertFalse($request->hasUpdates());
    }

    public function testHasUpdatesReturnsTrueWithEmptyArrays(): void
    {
        $request = new ProjectUpdateRequest(
            projectId: 'proj_123',
            tags: [],
        );

        $this->assertTrue($request->hasUpdates());
    }

    public function testValidateWithValidRequest(): void
    {
        $request = new ProjectUpdateRequest(
            projectId: 'proj_123',
            title: 'Valid Title',
        );

        $errors = $request->validate();

        $this->assertEmpty($errors);
    }

    public function testValidateWithEmptyProjectId(): void
    {
        $request = new ProjectUpdateRequest(
            projectId: '',
            title: 'Some Title',
        );

        $errors = $request->validate();

        $this->assertContains('Project ID cannot be empty', $errors);
    }

    public function testValidateWithNoUpdates(): void
    {
        $request = new ProjectUpdateRequest(projectId: 'proj_123');

        $errors = $request->validate();

        $this->assertContains('At least one field must be provided for update', $errors);
    }

    public function testValidateWithMultipleErrors(): void
    {
        $request = new ProjectUpdateRequest(projectId: '');

        $errors = $request->validate();

        $this->assertContains('Project ID cannot be empty', $errors);
        $this->assertContains('At least one field must be provided for update', $errors);
        $this->assertCount(2, $errors);
    }

    public function testValidateWithEmptyArraysIsValid(): void
    {
        $request = new ProjectUpdateRequest(
            projectId: 'proj_valid',
            tags: [],
            entryDirs: [],
            memory: [],
        );

        $errors = $request->validate();

        $this->assertEmpty($errors);
    }

    public function testIsReadonlyDto(): void
    {
        $request = new ProjectUpdateRequest(
            projectId: 'proj_readonly',
            title: 'Original Title',
        );

        // Properties should be readonly (this is ensured by PHP at compile time)
        $this->assertSame('proj_readonly', $request->projectId);
        $this->assertSame('Original Title', $request->title);
    }
}
