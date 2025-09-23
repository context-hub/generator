<?php

declare(strict_types=1);

namespace Tests\Unit\Drafling\MCP\Tools;

use Butschster\ContextGenerator\Drafling\Domain\Model\Project;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\ProjectId;
use Butschster\ContextGenerator\Drafling\Exception\DraflingException;
use Butschster\ContextGenerator\Drafling\Exception\ProjectNotFoundException;
use Butschster\ContextGenerator\Drafling\MCP\DTO\ProjectUpdateRequest;
use Butschster\ContextGenerator\Drafling\MCP\Tools\UpdateProjectToolAction;
use Butschster\ContextGenerator\Drafling\Service\ProjectServiceInterface;
use Mcp\Types\CallToolResult;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for UpdateProjectToolAction
 */
final class UpdateProjectToolActionTest extends TestCase
{
    private LoggerInterface&MockObject $logger;
    private ProjectServiceInterface&MockObject $projectService;
    private UpdateProjectToolAction $toolAction;

    public function testSuccessfulProjectUpdate(): void
    {
        $request = new ProjectUpdateRequest(
            projectId: 'proj_123',
            title: 'Updated Title',
            description: 'Updated description',
            status: 'active',
            tags: ['web', 'blog'],
            entryDirs: ['posts', 'pages'],
            memory: ['Updated memory'],
        );

        $updatedProject = new Project(
            id: 'proj_123',
            name: 'Updated Title',
            description: 'Updated description',
            template: 'blog-template',
            status: 'active',
            tags: ['web', 'blog'],
            entryDirs: ['posts', 'pages'],
            memory: ['Updated memory'],
        );

        $projectId = ProjectId::fromString('proj_123');

        $this->projectService
            ->expects($this->once())
            ->method('projectExists')
            ->with($projectId)
            ->willReturn(true);

        $this->projectService
            ->expects($this->once())
            ->method('updateProject')
            ->with($projectId, $request)
            ->willReturn($updatedProject);

        $result = ($this->toolAction)($request);

        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertFalse($result->isError);

        $content = $result->content[0];
        $responseData = \json_decode($content->text, true);

        $this->assertTrue($responseData['success']);
        $this->assertSame('proj_123', $responseData['project_id']);
        $this->assertSame('Updated Title', $responseData['title']);
        $this->assertSame('active', $responseData['status']);
        $this->assertSame('blog-template', $responseData['project_type']);

        // Check metadata
        $this->assertSame('Updated description', $responseData['metadata']['description']);
        $this->assertSame(['web', 'blog'], $responseData['metadata']['tags']);
        $this->assertSame(['posts', 'pages'], $responseData['metadata']['entry_dirs']);
        $this->assertSame(['Updated memory'], $responseData['metadata']['memory']);

        // Check changes applied
        $expectedChanges = ['title', 'description', 'status', 'tags', 'entry_directories', 'memory'];
        $this->assertSame($expectedChanges, $responseData['changes_applied']);
    }

    public function testPartialProjectUpdate(): void
    {
        $request = new ProjectUpdateRequest(
            projectId: 'proj_456',
            title: 'New Title Only',
        );

        $updatedProject = new Project(
            id: 'proj_456',
            name: 'New Title Only',
            description: 'Original description',
            template: 'simple-template',
            status: 'draft',
            tags: ['existing'],
            entryDirs: ['existing-dir'],
            memory: ['existing memory'],
        );

        $projectId = ProjectId::fromString('proj_456');

        $this->projectService
            ->expects($this->once())
            ->method('projectExists')
            ->with($projectId)
            ->willReturn(true);

        $this->projectService
            ->expects($this->once())
            ->method('updateProject')
            ->with($projectId, $request)
            ->willReturn($updatedProject);

        $result = ($this->toolAction)($request);

        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertFalse($result->isError);

        $content = $result->content[0];
        $responseData = \json_decode($content->text, true);

        $this->assertTrue($responseData['success']);
        $this->assertSame(['title'], $responseData['changes_applied']);
    }

    public function testValidationErrors(): void
    {
        $request = new ProjectUpdateRequest(projectId: ''); // Empty project ID

        $result = ($this->toolAction)($request);

        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertTrue($result->isError);

        $content = $result->content[0];
        $responseData = \json_decode($content->text, true);

        $this->assertFalse($responseData['success']);
        $this->assertSame('Validation failed', $responseData['error']);
        $this->assertIsArray($responseData['details']);
    }

    public function testProjectNotFound(): void
    {
        $request = new ProjectUpdateRequest(
            projectId: 'proj_nonexistent',
            title: 'New Title',
        );

        $projectId = ProjectId::fromString('proj_nonexistent');

        $this->projectService
            ->expects($this->once())
            ->method('projectExists')
            ->with($projectId)
            ->willReturn(false);

        $result = ($this->toolAction)($request);

        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertTrue($result->isError);

        $content = $result->content[0];
        $responseData = \json_decode($content->text, true);

        $this->assertFalse($responseData['success']);
        $this->assertSame("Project 'proj_nonexistent' not found", $responseData['error']);
    }

    public function testProjectNotFoundExceptionFromService(): void
    {
        $request = new ProjectUpdateRequest(
            projectId: 'proj_exception',
            title: 'New Title',
        );

        $projectId = ProjectId::fromString('proj_exception');

        $this->projectService
            ->expects($this->once())
            ->method('projectExists')
            ->with($projectId)
            ->willReturn(true);

        $this->projectService
            ->expects($this->once())
            ->method('updateProject')
            ->with($projectId, $request)
            ->willThrowException(new ProjectNotFoundException('Project not found in service'));

        $result = ($this->toolAction)($request);

        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertTrue($result->isError);

        $content = $result->content[0];
        $responseData = \json_decode($content->text, true);

        $this->assertFalse($responseData['success']);
        $this->assertSame('Project not found in service', $responseData['error']);
    }

    public function testDraflingException(): void
    {
        $request = new ProjectUpdateRequest(
            projectId: 'proj_error',
            title: 'New Title',
        );

        $projectId = ProjectId::fromString('proj_error');

        $this->projectService
            ->expects($this->once())
            ->method('projectExists')
            ->with($projectId)
            ->willReturn(true);

        $this->projectService
            ->expects($this->once())
            ->method('updateProject')
            ->with($projectId, $request)
            ->willThrowException(new DraflingException('Service error'));

        $result = ($this->toolAction)($request);

        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertTrue($result->isError);

        $content = $result->content[0];
        $responseData = \json_decode($content->text, true);

        $this->assertFalse($responseData['success']);
        $this->assertSame('Service error', $responseData['error']);
    }

    public function testUnexpectedException(): void
    {
        $request = new ProjectUpdateRequest(
            projectId: 'proj_unexpected',
            title: 'New Title',
        );

        $projectId = ProjectId::fromString('proj_unexpected');

        $this->projectService
            ->expects($this->once())
            ->method('projectExists')
            ->with($projectId)
            ->willReturn(true);

        $this->projectService
            ->expects($this->once())
            ->method('updateProject')
            ->with($projectId, $request)
            ->willThrowException(new \RuntimeException('Unexpected error'));

        $result = ($this->toolAction)($request);

        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertTrue($result->isError);

        $content = $result->content[0];
        $responseData = \json_decode($content->text, true);

        $this->assertFalse($responseData['success']);
        $this->assertSame('Failed to update project: Unexpected error', $responseData['error']);
    }

    public function testEmptyArrayUpdatesAreTracked(): void
    {
        $request = new ProjectUpdateRequest(
            projectId: 'proj_empty',
            tags: [],
            entryDirs: [],
            memory: [],
        );

        $updatedProject = new Project(
            id: 'proj_empty',
            name: 'Test Project',
            description: 'Description',
            template: 'template',
            status: 'draft',
            tags: [],
            entryDirs: [],
            memory: [],
        );

        $projectId = ProjectId::fromString('proj_empty');

        $this->projectService
            ->expects($this->once())
            ->method('projectExists')
            ->with($projectId)
            ->willReturn(true);

        $this->projectService
            ->expects($this->once())
            ->method('updateProject')
            ->with($projectId, $request)
            ->willReturn($updatedProject);

        $result = ($this->toolAction)($request);

        $content = $result->content[0];
        $responseData = \json_decode($content->text, true);

        $expectedChanges = ['tags', 'entry_directories', 'memory'];
        $this->assertSame($expectedChanges, $responseData['changes_applied']);
    }

    public function testLoggerIsCalled(): void
    {
        $request = new ProjectUpdateRequest(
            projectId: 'proj_log',
            title: 'Logged Update',
        );

        $updatedProject = new Project(
            id: 'proj_log',
            name: 'Logged Update',
            description: 'desc',
            template: 'template',
            status: 'draft',
            tags: [],
            entryDirs: [],
            memory: [],
        );

        $projectId = ProjectId::fromString('proj_log');

        $this->logger
            ->expects($this->exactly(2))
            ->method('info')
            ->with($this->logicalOr(
                $this->equalTo('Updating project'),
                $this->equalTo('Project updated successfully'),
            ));

        $this->projectService
            ->expects($this->once())
            ->method('projectExists')
            ->with($projectId)
            ->willReturn(true);

        $this->projectService
            ->expects($this->once())
            ->method('updateProject')
            ->with($projectId, $request)
            ->willReturn($updatedProject);

        ($this->toolAction)($request);
    }

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->projectService = $this->createMock(ProjectServiceInterface::class);

        $this->toolAction = new UpdateProjectToolAction(
            $this->logger,
            $this->projectService,
        );
    }
}
