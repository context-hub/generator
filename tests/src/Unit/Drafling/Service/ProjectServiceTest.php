<?php

declare(strict_types=1);

namespace Tests\Unit\Drafling\Service;

use Butschster\ContextGenerator\Drafling\Domain\Model\Project;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\ProjectId;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\TemplateKey;
use Butschster\ContextGenerator\Drafling\Exception\DraflingException;
use Butschster\ContextGenerator\Drafling\Exception\ProjectNotFoundException;
use Butschster\ContextGenerator\Drafling\Exception\TemplateNotFoundException;
use Butschster\ContextGenerator\Drafling\MCP\DTO\ProjectCreateRequest;
use Butschster\ContextGenerator\Drafling\MCP\DTO\ProjectUpdateRequest;
use Butschster\ContextGenerator\Drafling\Repository\ProjectRepositoryInterface;
use Butschster\ContextGenerator\Drafling\Service\ProjectService;
use Butschster\ContextGenerator\Drafling\Service\TemplateServiceInterface;
use Butschster\ContextGenerator\Drafling\Storage\StorageDriverInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for ProjectService
 */
final class ProjectServiceTest extends TestCase
{
    private ProjectRepositoryInterface&MockObject $projectRepository;
    private TemplateServiceInterface&MockObject $templateService;
    private StorageDriverInterface&MockObject $storageDriver;
    private LoggerInterface&MockObject $logger;
    private ProjectService $projectService;

    public function testCreateProjectSuccess(): void
    {
        $request = new ProjectCreateRequest(
            templateId: 'blog-template',
            title: 'My Blog',
        );

        $templateKey = TemplateKey::fromString('blog-template');
        $createdProject = new Project(
            id: 'proj_123',
            name: 'My Blog',
            description: '',
            template: 'blog-template',
            status: 'draft',
            tags: [],
            entryDirs: [],
        );

        // Template exists
        $this->templateService
            ->expects($this->once())
            ->method('templateExists')
            ->with($templateKey)
            ->willReturn(true);

        // Storage driver creates project
        $this->storageDriver
            ->expects($this->once())
            ->method('createProject')
            ->with($request)
            ->willReturn($createdProject);

        // Repository saves project
        $this->projectRepository
            ->expects($this->once())
            ->method('save')
            ->with($createdProject);

        $result = $this->projectService->createProject($request);

        $this->assertSame($createdProject, $result);
    }

    public function testCreateProjectWithNonExistentTemplate(): void
    {
        $request = new ProjectCreateRequest(
            templateId: 'non-existent-template',
            title: 'Test Project',
        );

        $templateKey = TemplateKey::fromString('non-existent-template');

        $this->templateService
            ->expects($this->once())
            ->method('templateExists')
            ->with($templateKey)
            ->willReturn(false);

        $this->expectException(TemplateNotFoundException::class);
        $this->expectExceptionMessage("Template 'non-existent-template' not found");

        $this->projectService->createProject($request);
    }

    public function testCreateProjectStorageFailure(): void
    {
        $request = new ProjectCreateRequest(
            templateId: 'valid-template',
            title: 'Test Project',
        );

        $templateKey = TemplateKey::fromString('valid-template');

        $this->templateService
            ->expects($this->once())
            ->method('templateExists')
            ->with($templateKey)
            ->willReturn(true);

        $this->storageDriver
            ->expects($this->once())
            ->method('createProject')
            ->with($request)
            ->willThrowException(new \RuntimeException('Storage error'));

        $this->expectException(DraflingException::class);
        $this->expectExceptionMessage('Failed to create project: Storage error');

        $this->projectService->createProject($request);
    }

    public function testUpdateProjectSuccess(): void
    {
        $projectId = ProjectId::fromString('proj_123');
        $request = new ProjectUpdateRequest(
            projectId: 'proj_123',
            title: 'Updated Title',
        );

        $updatedProject = new Project(
            id: 'proj_123',
            name: 'Updated Title',
            description: 'description',
            template: 'blog',
            status: 'draft',
            tags: [],
            entryDirs: [],
        );

        // Project exists
        $this->projectRepository
            ->expects($this->once())
            ->method('exists')
            ->with($projectId)
            ->willReturn(true);

        // Storage driver updates project
        $this->storageDriver
            ->expects($this->once())
            ->method('updateProject')
            ->with($projectId, $request)
            ->willReturn($updatedProject);

        // Repository saves updated project
        $this->projectRepository
            ->expects($this->once())
            ->method('save')
            ->with($updatedProject);

        $result = $this->projectService->updateProject($projectId, $request);

        $this->assertSame($updatedProject, $result);
    }

    public function testUpdateProjectNotFound(): void
    {
        $projectId = ProjectId::fromString('proj_nonexistent');
        $request = new ProjectUpdateRequest(
            projectId: 'proj_nonexistent',
            title: 'Updated Title',
        );

        $this->projectRepository
            ->expects($this->once())
            ->method('exists')
            ->with($projectId)
            ->willReturn(false);

        $this->expectException(ProjectNotFoundException::class);
        $this->expectExceptionMessage("Project 'proj_nonexistent' not found");

        $this->projectService->updateProject($projectId, $request);
    }

    public function testUpdateProjectStorageFailure(): void
    {
        $projectId = ProjectId::fromString('proj_123');
        $request = new ProjectUpdateRequest(
            projectId: 'proj_123',
            title: 'Updated Title',
        );

        $this->projectRepository
            ->expects($this->once())
            ->method('exists')
            ->with($projectId)
            ->willReturn(true);

        $this->storageDriver
            ->expects($this->once())
            ->method('updateProject')
            ->with($projectId, $request)
            ->willThrowException(new \RuntimeException('Update failed'));

        $this->expectException(DraflingException::class);
        $this->expectExceptionMessage('Failed to update project: Update failed');

        $this->projectService->updateProject($projectId, $request);
    }

    public function testProjectExists(): void
    {
        $projectId = ProjectId::fromString('proj_exists');

        $this->projectRepository
            ->expects($this->once())
            ->method('exists')
            ->with($projectId)
            ->willReturn(true);

        $result = $this->projectService->projectExists($projectId);

        $this->assertTrue($result);
    }

    public function testProjectNotExists(): void
    {
        $projectId = ProjectId::fromString('proj_notexists');

        $this->projectRepository
            ->expects($this->once())
            ->method('exists')
            ->with($projectId)
            ->willReturn(false);

        $result = $this->projectService->projectExists($projectId);

        $this->assertFalse($result);
    }

    public function testGetProject(): void
    {
        $projectId = ProjectId::fromString('proj_get');
        $project = new Project(
            id: 'proj_get',
            name: 'Test Project',
            description: 'description',
            template: 'blog',
            status: 'active',
            tags: [],
            entryDirs: [],
        );

        $this->projectRepository
            ->expects($this->once())
            ->method('findById')
            ->with($projectId)
            ->willReturn($project);

        $result = $this->projectService->getProject($projectId);

        $this->assertSame($project, $result);
    }

    public function testGetProjectNotFound(): void
    {
        $projectId = ProjectId::fromString('proj_notfound');

        $this->projectRepository
            ->expects($this->once())
            ->method('findById')
            ->with($projectId)
            ->willReturn(null);

        $result = $this->projectService->getProject($projectId);

        $this->assertNull($result);
    }

    public function testListProjects(): void
    {
        $filters = ['status' => 'active'];
        $projects = [
            new Project(
                id: 'proj_1',
                name: 'Project 1',
                description: 'desc1',
                template: 'blog',
                status: 'active',
                tags: [],
                entryDirs: [],
            ),
            new Project(
                id: 'proj_2',
                name: 'Project 2',
                description: 'desc2',
                template: 'portfolio',
                status: 'active',
                tags: [],
                entryDirs: [],
            ),
        ];

        $this->projectRepository
            ->expects($this->once())
            ->method('findAll')
            ->with($filters)
            ->willReturn($projects);

        $result = $this->projectService->listProjects($filters);

        $this->assertSame($projects, $result);
    }

    public function testListProjectsFailure(): void
    {
        $filters = ['status' => 'active'];

        $this->projectRepository
            ->expects($this->once())
            ->method('findAll')
            ->with($filters)
            ->willThrowException(new \RuntimeException('Database error'));

        $this->expectException(DraflingException::class);
        $this->expectExceptionMessage('Failed to list projects: Database error');

        $this->projectService->listProjects($filters);
    }

    public function testDeleteProjectSuccess(): void
    {
        $projectId = ProjectId::fromString('proj_delete');

        // Project exists
        $this->projectRepository
            ->expects($this->once())
            ->method('exists')
            ->with($projectId)
            ->willReturn(true);

        // Storage driver deletes project
        $this->storageDriver
            ->expects($this->once())
            ->method('deleteProject')
            ->with($projectId)
            ->willReturn(true);

        // Repository removes project
        $this->projectRepository
            ->expects($this->once())
            ->method('delete')
            ->with($projectId);

        $result = $this->projectService->deleteProject($projectId);

        $this->assertTrue($result);
    }

    public function testDeleteProjectNotFound(): void
    {
        $projectId = ProjectId::fromString('proj_notexist');

        $this->projectRepository
            ->expects($this->once())
            ->method('exists')
            ->with($projectId)
            ->willReturn(false);

        $result = $this->projectService->deleteProject($projectId);

        $this->assertFalse($result);
    }

    public function testDeleteProjectStorageFailure(): void
    {
        $projectId = ProjectId::fromString('proj_storage_fail');

        $this->projectRepository
            ->expects($this->once())
            ->method('exists')
            ->with($projectId)
            ->willReturn(true);

        $this->storageDriver
            ->expects($this->once())
            ->method('deleteProject')
            ->with($projectId)
            ->willThrowException(new \RuntimeException('Delete failed'));

        $this->expectException(DraflingException::class);
        $this->expectExceptionMessage('Failed to delete project: Delete failed');

        $this->projectService->deleteProject($projectId);
    }

    public function testAddProjectMemorySuccess(): void
    {
        $projectId = ProjectId::fromString('proj_memory');
        $memory = 'New memory entry';

        $originalProject = new Project(
            id: 'proj_memory',
            name: 'Memory Test',
            description: 'desc',
            template: 'blog',
            status: 'active',
            tags: [],
            entryDirs: [],
            memory: ['existing memory'],
        );

        $updatedProject = new Project(
            id: 'proj_memory',
            name: 'Memory Test',
            description: 'desc',
            template: 'blog',
            status: 'active',
            tags: [],
            entryDirs: [],
            memory: ['existing memory', 'New memory entry'],
        );

        $this->projectRepository
            ->expects($this->once())
            ->method('findById')
            ->with($projectId)
            ->willReturn($originalProject);

        $this->projectRepository
            ->expects($this->once())
            ->method('save')
            ->with($updatedProject);

        $result = $this->projectService->addProjectMemory($projectId, $memory);

        $this->assertEquals($updatedProject->memory, $result->memory);
        $this->assertContains('New memory entry', $result->memory);
        $this->assertContains('existing memory', $result->memory);
    }

    public function testAddProjectMemoryProjectNotFound(): void
    {
        $projectId = ProjectId::fromString('proj_notexist');
        $memory = 'Some memory';

        $this->projectRepository
            ->expects($this->once())
            ->method('findById')
            ->with($projectId)
            ->willReturn(null);

        $this->expectException(ProjectNotFoundException::class);
        $this->expectExceptionMessage("Project 'proj_notexist' not found");

        $this->projectService->addProjectMemory($projectId, $memory);
    }

    public function testAddProjectMemoryRepositoryFailure(): void
    {
        $projectId = ProjectId::fromString('proj_memory_fail');
        $memory = 'Memory content';

        $project = new Project(
            id: 'proj_memory_fail',
            name: 'Test',
            description: 'desc',
            template: 'blog',
            status: 'active',
            tags: [],
            entryDirs: [],
            memory: [],
        );

        $this->projectRepository
            ->expects($this->once())
            ->method('findById')
            ->with($projectId)
            ->willReturn($project);

        $this->projectRepository
            ->expects($this->once())
            ->method('save')
            ->willThrowException(new \RuntimeException('Save failed'));

        $this->expectException(DraflingException::class);
        $this->expectExceptionMessage('Failed to add memory to project: Save failed');

        $this->projectService->addProjectMemory($projectId, $memory);
    }

    protected function setUp(): void
    {
        $this->projectRepository = $this->createMock(ProjectRepositoryInterface::class);
        $this->templateService = $this->createMock(TemplateServiceInterface::class);
        $this->storageDriver = $this->createMock(StorageDriverInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->projectService = new ProjectService(
            $this->projectRepository,
            $this->templateService,
            $this->storageDriver,
            $this->logger,
        );
    }
}
