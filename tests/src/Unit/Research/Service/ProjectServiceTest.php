<?php

declare(strict_types=1);

namespace Tests\Unit\Research\Service;

use Butschster\ContextGenerator\Research\Domain\Model\Research;
use Butschster\ContextGenerator\Research\Domain\ValueObject\ResearchId;
use Butschster\ContextGenerator\Research\Domain\ValueObject\TemplateKey;
use Butschster\ContextGenerator\Research\Exception\ResearchException;
use Butschster\ContextGenerator\Research\Exception\ResearchNotFoundException;
use Butschster\ContextGenerator\Research\Exception\TemplateNotFoundException;
use Butschster\ContextGenerator\Research\MCP\DTO\ResearchCreateRequest;
use Butschster\ContextGenerator\Research\MCP\DTO\ResearchUpdateRequest;
use Butschster\ContextGenerator\Research\Repository\ResearchRepositoryInterface;
use Butschster\ContextGenerator\Research\Service\ResearchService;
use Butschster\ContextGenerator\Research\Service\TemplateServiceInterface;
use Butschster\ContextGenerator\Research\Storage\StorageDriverInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for ProjectService
 */
final class ProjectServiceTest extends TestCase
{
    private ResearchRepositoryInterface&MockObject $projectRepository;
    private TemplateServiceInterface&MockObject $templateService;
    private StorageDriverInterface&MockObject $storageDriver;
    private LoggerInterface&MockObject $logger;
    private ResearchService $projectService;

    public function testCreateProjectSuccess(): void
    {
        $request = new ResearchCreateRequest(
            templateId: 'blog-template',
            title: 'My Blog',
        );

        $templateKey = TemplateKey::fromString('blog-template');
        $createdProject = new Research(
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
            ->method('createResearch')
            ->with($request)
            ->willReturn($createdProject);

        // Repository saves project
        $this->projectRepository
            ->expects($this->once())
            ->method('save')
            ->with($createdProject);

        $result = $this->projectService->create($request);

        $this->assertSame($createdProject, $result);
    }

    public function testCreateProjectWithNonExistentTemplate(): void
    {
        $request = new ResearchCreateRequest(
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

        $this->projectService->create($request);
    }

    public function testCreateProjectStorageFailure(): void
    {
        $request = new ResearchCreateRequest(
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
            ->method('createResearch')
            ->with($request)
            ->willThrowException(new \RuntimeException('Storage error'));

        $this->expectException(ResearchException::class);
        $this->expectExceptionMessage('Failed to create research: Storage error');

        $this->projectService->create($request);
    }

    public function testUpdateProjectSuccess(): void
    {
        $projectId = ResearchId::fromString('proj_123');
        $request = new ResearchUpdateRequest(
            researchId: 'proj_123',
            title: 'Updated Title',
        );

        $updatedProject = new Research(
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
            ->method('updateResearch')
            ->with($projectId, $request)
            ->willReturn($updatedProject);

        // Repository saves updated project
        $this->projectRepository
            ->expects($this->once())
            ->method('save')
            ->with($updatedProject);

        $result = $this->projectService->update($projectId, $request);

        $this->assertSame($updatedProject, $result);
    }

    public function testUpdateProjectNotFound(): void
    {
        $projectId = ResearchId::fromString('proj_nonexistent');
        $request = new ResearchUpdateRequest(
            researchId: 'proj_nonexistent',
            title: 'Updated Title',
        );

        $this->projectRepository
            ->expects($this->once())
            ->method('exists')
            ->with($projectId)
            ->willReturn(false);

        $this->expectException(ResearchNotFoundException::class);
        $this->expectExceptionMessage("Research 'proj_nonexistent' not found");

        $this->projectService->update($projectId, $request);
    }

    public function testUpdateProjectStorageFailure(): void
    {
        $projectId = ResearchId::fromString('proj_123');
        $request = new ResearchUpdateRequest(
            researchId: 'proj_123',
            title: 'Updated Title',
        );

        $this->projectRepository
            ->expects($this->once())
            ->method('exists')
            ->with($projectId)
            ->willReturn(true);

        $this->storageDriver
            ->expects($this->once())
            ->method('updateResearch')
            ->with($projectId, $request)
            ->willThrowException(new \RuntimeException('Update failed'));

        $this->expectException(ResearchException::class);
        $this->expectExceptionMessage('Failed to update research: Update failed');

        $this->projectService->update($projectId, $request);
    }

    public function testProjectExists(): void
    {
        $projectId = ResearchId::fromString('proj_exists');

        $this->projectRepository
            ->expects($this->once())
            ->method('exists')
            ->with($projectId)
            ->willReturn(true);

        $result = $this->projectService->exists($projectId);

        $this->assertTrue($result);
    }

    public function testProjectNotExists(): void
    {
        $projectId = ResearchId::fromString('proj_notexists');

        $this->projectRepository
            ->expects($this->once())
            ->method('exists')
            ->with($projectId)
            ->willReturn(false);

        $result = $this->projectService->exists($projectId);

        $this->assertFalse($result);
    }

    public function testGetProject(): void
    {
        $projectId = ResearchId::fromString('proj_get');
        $project = new Research(
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

        $result = $this->projectService->get($projectId);

        $this->assertSame($project, $result);
    }

    public function testGetProjectNotFound(): void
    {
        $projectId = ResearchId::fromString('proj_notfound');

        $this->projectRepository
            ->expects($this->once())
            ->method('findById')
            ->with($projectId)
            ->willReturn(null);

        $result = $this->projectService->get($projectId);

        $this->assertNull($result);
    }

    public function testListProjects(): void
    {
        $filters = ['status' => 'active'];
        $projects = [
            new Research(
                id: 'proj_1',
                name: 'Project 1',
                description: 'desc1',
                template: 'blog',
                status: 'active',
                tags: [],
                entryDirs: [],
            ),
            new Research(
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

        $result = $this->projectService->findAll($filters);

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

        $this->expectException(ResearchException::class);
        $this->expectExceptionMessage('Failed to list researches: Database error');

        $this->projectService->findAll($filters);
    }

    public function testDeleteProjectSuccess(): void
    {
        $projectId = ResearchId::fromString('proj_delete');

        // Project exists
        $this->projectRepository
            ->expects($this->once())
            ->method('exists')
            ->with($projectId)
            ->willReturn(true);

        // Storage driver deletes project
        $this->storageDriver
            ->expects($this->once())
            ->method('deleteResearch')
            ->with($projectId)
            ->willReturn(true);

        // Repository removes project
        $this->projectRepository
            ->expects($this->once())
            ->method('delete')
            ->with($projectId);

        $result = $this->projectService->delete($projectId);

        $this->assertTrue($result);
    }

    public function testDeleteProjectNotFound(): void
    {
        $projectId = ResearchId::fromString('proj_notexist');

        $this->projectRepository
            ->expects($this->once())
            ->method('exists')
            ->with($projectId)
            ->willReturn(false);

        $result = $this->projectService->delete($projectId);

        $this->assertFalse($result);
    }

    public function testDeleteProjectStorageFailure(): void
    {
        $projectId = ResearchId::fromString('proj_storage_fail');

        $this->projectRepository
            ->expects($this->once())
            ->method('exists')
            ->with($projectId)
            ->willReturn(true);

        $this->storageDriver
            ->expects($this->once())
            ->method('deleteResearch')
            ->with($projectId)
            ->willThrowException(new \RuntimeException('Delete failed'));

        $this->expectException(ResearchException::class);
        $this->expectExceptionMessage('Failed to delete research: Delete failed');

        $this->projectService->delete($projectId);
    }

    public function testAddProjectMemorySuccess(): void
    {
        $projectId = ResearchId::fromString('proj_memory');
        $memory = 'New memory entry';

        $originalProject = new Research(
            id: 'proj_memory',
            name: 'Memory Test',
            description: 'desc',
            template: 'blog',
            status: 'active',
            tags: [],
            entryDirs: [],
            memory: ['existing memory'],
        );

        $updatedProject = new Research(
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

        $result = $this->projectService->addMemory($projectId, $memory);

        $this->assertEquals($updatedProject->memory, $result->memory);
        $this->assertContains('New memory entry', $result->memory);
        $this->assertContains('existing memory', $result->memory);
    }

    public function testAddProjectMemoryProjectNotFound(): void
    {
        $projectId = ResearchId::fromString('proj_notexist');
        $memory = 'Some memory';

        $this->projectRepository
            ->expects($this->once())
            ->method('findById')
            ->with($projectId)
            ->willReturn(null);

        $this->expectException(ResearchNotFoundException::class);
        $this->expectExceptionMessage("Research 'proj_notexist' not found");

        $this->projectService->addMemory($projectId, $memory);
    }

    public function testAddProjectMemoryRepositoryFailure(): void
    {
        $projectId = ResearchId::fromString('proj_memory_fail');
        $memory = 'Memory content';

        $project = new Research(
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

        $this->expectException(ResearchException::class);
        $this->expectExceptionMessage('Failed to add memory to research: Save failed');

        $this->projectService->addMemory($projectId, $memory);
    }

    protected function setUp(): void
    {
        $this->projectRepository = $this->createMock(ResearchRepositoryInterface::class);
        $this->templateService = $this->createMock(TemplateServiceInterface::class);
        $this->storageDriver = $this->createMock(StorageDriverInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->projectService = new ResearchService(
            $this->projectRepository,
            $this->templateService,
            $this->storageDriver,
            $this->logger,
        );
    }
}
