<?php

declare(strict_types=1);

namespace Tests\Unit\Drafling\MCP\Tools;

use Butschster\ContextGenerator\Drafling\Domain\Model\Project;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\ProjectId;
use Butschster\ContextGenerator\Drafling\Exception\DraflingException;
use Butschster\ContextGenerator\Drafling\Exception\ProjectNotFoundException;
use Butschster\ContextGenerator\Drafling\MCP\DTO\AddProjectMemoryRequest;
use Butschster\ContextGenerator\Drafling\MCP\Tools\AddProjectMemoryToolAction;
use Butschster\ContextGenerator\Drafling\Service\ProjectServiceInterface;
use Mcp\Types\CallToolResult;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for AddProjectMemoryToolAction
 */
final class AddProjectMemoryToolActionTest extends TestCase
{
    private LoggerInterface&MockObject $logger;
    private ProjectServiceInterface&MockObject $projectService;
    private AddProjectMemoryToolAction $toolAction;

    public function testSuccessfulMemoryAddition(): void
    {
        $request = new AddProjectMemoryRequest(
            projectId: 'proj_123',
            memory: 'This is a new memory entry for the project',
        );

        $updatedProject = new Project(
            id: 'proj_123',
            name: 'Test Project',
            description: 'Project description',
            template: 'blog-template',
            status: 'active',
            tags: ['web', 'blog'],
            entryDirs: ['posts'],
            memory: ['Existing memory', 'This is a new memory entry for the project'],
        );

        $projectId = ProjectId::fromString('proj_123');

        $this->projectService
            ->expects($this->once())
            ->method('projectExists')
            ->with($projectId)
            ->willReturn(true);

        $this->projectService
            ->expects($this->once())
            ->method('addProjectMemory')
            ->with($projectId, 'This is a new memory entry for the project')
            ->willReturn($updatedProject);

        $result = ($this->toolAction)($request);

        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertFalse($result->isError);

        $content = $result->content[0];
        $responseData = \json_decode($content->text, true);

        $this->assertTrue($responseData['success']);
        $this->assertSame('proj_123', $responseData['project_id']);
        $this->assertSame('Test Project', $responseData['title']);
        $this->assertSame('active', $responseData['status']);
        $this->assertSame('blog-template', $responseData['project_type']);
        $this->assertSame(2, $responseData['memory_count']);
        $this->assertSame('This is a new memory entry for the project', $responseData['memory_added']);

        // Check metadata
        $this->assertSame('Project description', $responseData['metadata']['description']);
        $this->assertSame(['web', 'blog'], $responseData['metadata']['tags']);
        $this->assertSame(['posts'], $responseData['metadata']['entry_dirs']);
        $this->assertSame(['Existing memory', 'This is a new memory entry for the project'], $responseData['metadata']['memory']);

        // Check timestamp is present
        $this->assertArrayHasKey('updated_at', $responseData);
    }

    public function testAddMemoryToEmptyMemoryArray(): void
    {
        $request = new AddProjectMemoryRequest(
            projectId: 'proj_empty',
            memory: 'First memory entry',
        );

        $updatedProject = new Project(
            id: 'proj_empty',
            name: 'Empty Memory Project',
            description: 'No previous memories',
            template: 'simple',
            status: 'draft',
            tags: [],
            entryDirs: [],
            memory: ['First memory entry'],
        );

        $projectId = ProjectId::fromString('proj_empty');

        $this->projectService
            ->expects($this->once())
            ->method('projectExists')
            ->with($projectId)
            ->willReturn(true);

        $this->projectService
            ->expects($this->once())
            ->method('addProjectMemory')
            ->with($projectId, 'First memory entry')
            ->willReturn($updatedProject);

        $result = ($this->toolAction)($request);

        $content = $result->content[0];
        $responseData = \json_decode($content->text, true);

        $this->assertTrue($responseData['success']);
        $this->assertSame(1, $responseData['memory_count']);
        $this->assertSame(['First memory entry'], $responseData['metadata']['memory']);
    }

    public function testValidationErrorEmptyProjectId(): void
    {
        $request = new AddProjectMemoryRequest(
            projectId: '',
            memory: 'Valid memory',
        );

        $result = ($this->toolAction)($request);

        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertTrue($result->isError);

        $content = $result->content[0];
        $responseData = \json_decode($content->text, true);

        $this->assertFalse($responseData['success']);
        $this->assertSame('Validation failed', $responseData['error']);
        $this->assertContains('Project ID cannot be empty', $responseData['details']);
    }

    public function testValidationErrorEmptyMemory(): void
    {
        $request = new AddProjectMemoryRequest(
            projectId: 'proj_123',
            memory: '',
        );

        $result = ($this->toolAction)($request);

        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertTrue($result->isError);

        $content = $result->content[0];
        $responseData = \json_decode($content->text, true);

        $this->assertFalse($responseData['success']);
        $this->assertSame('Validation failed', $responseData['error']);
        $this->assertContains('Memory entry cannot be empty', $responseData['details']);
    }

    public function testValidationErrorMultipleErrors(): void
    {
        $request = new AddProjectMemoryRequest(
            projectId: '',
            memory: '   ',
        );

        $result = ($this->toolAction)($request);

        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertTrue($result->isError);

        $content = $result->content[0];
        $responseData = \json_decode($content->text, true);

        $this->assertFalse($responseData['success']);
        $this->assertSame('Validation failed', $responseData['error']);
        $this->assertContains('Project ID cannot be empty', $responseData['details']);
        $this->assertContains('Memory entry cannot be empty', $responseData['details']);
    }

    public function testProjectNotFound(): void
    {
        $request = new AddProjectMemoryRequest(
            projectId: 'proj_nonexistent',
            memory: 'Memory for non-existent project',
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
        $request = new AddProjectMemoryRequest(
            projectId: 'proj_exception',
            memory: 'Memory content',
        );

        $projectId = ProjectId::fromString('proj_exception');

        $this->projectService
            ->expects($this->once())
            ->method('projectExists')
            ->with($projectId)
            ->willReturn(true);

        $this->projectService
            ->expects($this->once())
            ->method('addProjectMemory')
            ->with($projectId, 'Memory content')
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
        $request = new AddProjectMemoryRequest(
            projectId: 'proj_error',
            memory: 'Memory content',
        );

        $projectId = ProjectId::fromString('proj_error');

        $this->projectService
            ->expects($this->once())
            ->method('projectExists')
            ->with($projectId)
            ->willReturn(true);

        $this->projectService
            ->expects($this->once())
            ->method('addProjectMemory')
            ->with($projectId, 'Memory content')
            ->willThrowException(new DraflingException('Service error occurred'));

        $result = ($this->toolAction)($request);

        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertTrue($result->isError);

        $content = $result->content[0];
        $responseData = \json_decode($content->text, true);

        $this->assertFalse($responseData['success']);
        $this->assertSame('Service error occurred', $responseData['error']);
    }

    public function testUnexpectedException(): void
    {
        $request = new AddProjectMemoryRequest(
            projectId: 'proj_unexpected',
            memory: 'Memory content',
        );

        $projectId = ProjectId::fromString('proj_unexpected');

        $this->projectService
            ->expects($this->once())
            ->method('projectExists')
            ->with($projectId)
            ->willReturn(true);

        $this->projectService
            ->expects($this->once())
            ->method('addProjectMemory')
            ->with($projectId, 'Memory content')
            ->willThrowException(new \RuntimeException('Unexpected system error'));

        $result = ($this->toolAction)($request);

        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertTrue($result->isError);

        $content = $result->content[0];
        $responseData = \json_decode($content->text, true);

        $this->assertFalse($responseData['success']);
        $this->assertSame('Failed to add memory to project: Unexpected system error', $responseData['error']);
    }

    public function testLoggerIsCalled(): void
    {
        $request = new AddProjectMemoryRequest(
            projectId: 'proj_log',
            memory: 'Logged memory entry',
        );

        $updatedProject = new Project(
            id: 'proj_log',
            name: 'Log Test Project',
            description: 'Testing logging',
            template: 'test',
            status: 'active',
            tags: [],
            entryDirs: [],
            memory: ['Logged memory entry'],
        );

        $projectId = ProjectId::fromString('proj_log');

        $this->logger
            ->expects($this->exactly(2))
            ->method('info')
            ->with($this->logicalOr(
                $this->equalTo('Adding memory to project'),
                $this->equalTo('Memory added to project successfully'),
            ));

        $this->projectService
            ->expects($this->once())
            ->method('projectExists')
            ->with($projectId)
            ->willReturn(true);

        $this->projectService
            ->expects($this->once())
            ->method('addProjectMemory')
            ->with($projectId, 'Logged memory entry')
            ->willReturn($updatedProject);

        ($this->toolAction)($request);
    }

    public function testMemoryWithSpecialCharacters(): void
    {
        $specialMemory = 'Memory with special chars: !@#$%^&*()_+-={}[]|\\:";\'<>?,./ and unicode: 🚀 世界';

        $request = new AddProjectMemoryRequest(
            projectId: 'proj_special',
            memory: $specialMemory,
        );

        $updatedProject = new Project(
            id: 'proj_special',
            name: 'Special Chars Project',
            description: 'Testing special characters',
            template: 'test',
            status: 'active',
            tags: [],
            entryDirs: [],
            memory: [$specialMemory],
        );

        $projectId = ProjectId::fromString('proj_special');

        $this->projectService
            ->expects($this->once())
            ->method('projectExists')
            ->with($projectId)
            ->willReturn(true);

        $this->projectService
            ->expects($this->once())
            ->method('addProjectMemory')
            ->with($projectId, $specialMemory)
            ->willReturn($updatedProject);

        $result = ($this->toolAction)($request);

        $content = $result->content[0];
        $responseData = \json_decode($content->text, true);

        $this->assertTrue($responseData['success']);
        $this->assertSame($specialMemory, $responseData['memory_added']);
        $this->assertSame([$specialMemory], $responseData['metadata']['memory']);
    }

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->projectService = $this->createMock(ProjectServiceInterface::class);

        $this->toolAction = new AddProjectMemoryToolAction(
            $this->logger,
            $this->projectService,
        );
    }
}
