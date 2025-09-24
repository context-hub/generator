<?php

declare(strict_types=1);

namespace Tests\Unit\Research\MCP\Tools;

use Butschster\ContextGenerator\Research\Domain\Model\Research;
use Butschster\ContextGenerator\Research\Domain\ValueObject\ResearchId;
use Butschster\ContextGenerator\Research\Exception\ResearchException;
use Butschster\ContextGenerator\Research\Exception\ResearchNotFoundException;
use Butschster\ContextGenerator\Research\MCP\DTO\ResearchUpdateRequest;
use Butschster\ContextGenerator\Research\MCP\Tools\UpdateResearchToolAction;
use Butschster\ContextGenerator\Research\Service\ResearchServiceInterface;
use Mcp\Types\CallToolResult;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for UpdateProjectToolAction
 */
final class UpdateResearchToolActionTest extends TestCase
{
    private LoggerInterface&MockObject $logger;
    private ResearchServiceInterface&MockObject $projectService;
    private UpdateResearchToolAction $toolAction;

    public function testSuccessfulProjectUpdate(): void
    {
        $request = new ResearchUpdateRequest(
            researchId: 'proj_123',
            title: 'Updated Title',
            description: 'Updated description',
            status: 'active',
            tags: ['web', 'blog'],
            entryDirs: ['posts', 'pages'],
            memory: ['Updated memory'],
        );

        $updatedProject = new Research(
            id: 'proj_123',
            name: 'Updated Title',
            description: 'Updated description',
            template: 'blog-template',
            status: 'active',
            tags: ['web', 'blog'],
            entryDirs: ['posts', 'pages'],
            memory: ['Updated memory'],
        );

        $projectId = ResearchId::fromString('proj_123');

        $this->projectService
            ->expects($this->once())
            ->method('exists')
            ->with($projectId)
            ->willReturn(true);

        $this->projectService
            ->expects($this->once())
            ->method('update')
            ->with($projectId, $request)
            ->willReturn($updatedProject);

        $result = ($this->toolAction)($request);

        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertFalse($result->isError);

        $content = $result->content[0];
        $responseData = \json_decode($content->text, true);

        $this->assertTrue($responseData['success']);
    }

    public function testPartialProjectUpdate(): void
    {
        $request = new ResearchUpdateRequest(
            researchId: 'proj_456',
            title: 'New Title Only',
        );

        $updatedProject = new Research(
            id: 'proj_456',
            name: 'New Title Only',
            description: 'Original description',
            template: 'simple-template',
            status: 'draft',
            tags: ['existing'],
            entryDirs: ['existing-dir'],
            memory: ['existing memory'],
        );

        $projectId = ResearchId::fromString('proj_456');

        $this->projectService
            ->expects($this->once())
            ->method('exists')
            ->with($projectId)
            ->willReturn(true);

        $this->projectService
            ->expects($this->once())
            ->method('update')
            ->with($projectId, $request)
            ->willReturn($updatedProject);

        $result = ($this->toolAction)($request);

        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertFalse($result->isError);

        $content = $result->content[0];
        $responseData = \json_decode($content->text, true);

        $this->assertTrue($responseData['success']);
    }

    public function testValidationErrors(): void
    {
        $request = new ResearchUpdateRequest(researchId: ''); // Empty project ID

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
        $request = new ResearchUpdateRequest(
            researchId: 'proj_nonexistent',
            title: 'New Title',
        );

        $projectId = ResearchId::fromString('proj_nonexistent');

        $this->projectService
            ->expects($this->once())
            ->method('exists')
            ->with($projectId)
            ->willReturn(false);

        $result = ($this->toolAction)($request);

        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertTrue($result->isError);

        $content = $result->content[0];
        $responseData = \json_decode($content->text, true);

        $this->assertFalse($responseData['success']);
        $this->assertSame("Research 'proj_nonexistent' not found", $responseData['error']);
    }

    public function testProjectNotFoundExceptionFromService(): void
    {
        $request = new ResearchUpdateRequest(
            researchId: 'proj_exception',
            title: 'New Title',
        );

        $projectId = ResearchId::fromString('proj_exception');

        $this->projectService
            ->expects($this->once())
            ->method('exists')
            ->with($projectId)
            ->willReturn(true);

        $this->projectService
            ->expects($this->once())
            ->method('update')
            ->with($projectId, $request)
            ->willThrowException(new ResearchNotFoundException('Project not found in service'));

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
        $request = new ResearchUpdateRequest(
            researchId: 'proj_error',
            title: 'New Title',
        );

        $projectId = ResearchId::fromString('proj_error');

        $this->projectService
            ->expects($this->once())
            ->method('exists')
            ->with($projectId)
            ->willReturn(true);

        $this->projectService
            ->expects($this->once())
            ->method('update')
            ->with($projectId, $request)
            ->willThrowException(new ResearchException('Service error'));

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
        $request = new ResearchUpdateRequest(
            researchId: 'proj_unexpected',
            title: 'New Title',
        );

        $projectId = ResearchId::fromString('proj_unexpected');

        $this->projectService
            ->expects($this->once())
            ->method('exists')
            ->with($projectId)
            ->willReturn(true);

        $this->projectService
            ->expects($this->once())
            ->method('update')
            ->with($projectId, $request)
            ->willThrowException(new \RuntimeException('Unexpected error'));

        $result = ($this->toolAction)($request);

        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertTrue($result->isError);

        $content = $result->content[0];
        $responseData = \json_decode($content->text, true);

        $this->assertFalse($responseData['success']);
        $this->assertSame('Failed to update research: Unexpected error', $responseData['error']);
    }

    public function testEmptyArrayUpdatesAreTracked(): void
    {
        $request = new ResearchUpdateRequest(
            researchId: 'proj_empty',
            tags: [],
            entryDirs: [],
            memory: [],
        );

        $updatedProject = new Research(
            id: 'proj_empty',
            name: 'Test Project',
            description: 'Description',
            template: 'template',
            status: 'draft',
            tags: [],
            entryDirs: [],
            memory: [],
        );

        $projectId = ResearchId::fromString('proj_empty');

        $this->projectService
            ->expects($this->once())
            ->method('exists')
            ->with($projectId)
            ->willReturn(true);

        $this->projectService
            ->expects($this->once())
            ->method('update')
            ->with($projectId, $request)
            ->willReturn($updatedProject);

        $result = ($this->toolAction)($request);

        $content = $result->content[0];
        $responseData = \json_decode($content->text, true);
        $this->assertTrue($responseData['success']);
    }

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->projectService = $this->createMock(ResearchServiceInterface::class);

        $this->toolAction = new UpdateResearchToolAction(
            $this->logger,
            $this->projectService,
        );
    }
}
