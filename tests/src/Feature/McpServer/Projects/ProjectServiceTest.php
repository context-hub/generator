<?php

declare(strict_types=1);

namespace Tests\Feature\McpServer\Projects;

use Butschster\ContextGenerator\McpServer\Projects\DTO\CurrentProjectDTO;
use Butschster\ContextGenerator\McpServer\Projects\DTO\ProjectDTO;
use Butschster\ContextGenerator\McpServer\Projects\DTO\ProjectStateDTO;
use Butschster\ContextGenerator\McpServer\Projects\ProjectService;
use Butschster\ContextGenerator\McpServer\Projects\Repository\ProjectStateRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Spiral\Files\FilesInterface;
use Tests\TestCase;

final class ProjectServiceTest extends TestCase
{
    private ProjectService $service;
    private ProjectStateRepositoryInterface&MockObject $repository;
    private FilesInterface&MockObject $files;
    private LoggerInterface&MockObject $logger;
    private ProjectStateDTO $state;

    #[Test]
    public function it_returns_null_when_no_current_project(): void
    {
        $this->assertNull($this->service->getCurrentProject());
    }

    #[Test]
    public function it_returns_null_when_current_project_path_does_not_exist(): void
    {
        // Set a current project
        $this->state->currentProject = new CurrentProjectDTO(
            path: '/path/to/nonexistent/project',
        );

        // Configure files mock to report the path doesn't exist
        $this->files->method('exists')->with('/path/to/nonexistent/project')->willReturn(false);

        $this->assertNull($this->service->getCurrentProject());
    }

    #[Test]
    public function it_returns_current_project_when_exists(): void
    {
        // Set a current project
        $projectPath = '/path/to/existing/project';
        $this->state->currentProject = new CurrentProjectDTO(
            path: $projectPath,
            configFile: 'custom-config.yaml',
            envFile: '.env.local',
        );

        // Configure files mock to report the path exists
        $this->files->method('exists')->with($projectPath)->willReturn(true);

        $currentProject = $this->service->getCurrentProject();

        $this->assertNotNull($currentProject);
        $this->assertSame($projectPath, $currentProject->path);
        $this->assertSame('/path/to/existing/project/custom-config.yaml', $currentProject->getConfigFile());
        $this->assertSame('/path/to/existing/project/.env.local', $currentProject->getEnvFile());
    }

    #[Test]
    public function it_can_set_current_project(): void
    {
        $projectPath = '/path/to/project';
        $alias = 'my-project';
        $configFile = 'context.yaml';
        $envFile = '.env.test';

        // Configure repository to expect save call
        $this->repository->expects($this->once())->method('save');

        $this->service->setCurrentProject(
            projectPath: $projectPath,
            alias: $alias,
            configFile: $configFile,
            envFile: $envFile,
        );

        // Verify current project was set correctly
        $this->assertNotNull($this->state->currentProject);
        $this->assertSame($projectPath, $this->state->currentProject->path);
        $this->assertSame('/path/to/project/' . $configFile, $this->state->currentProject->getConfigFile());
        $this->assertSame('/path/to/project/' . $envFile, $this->state->currentProject->getEnvFile());

        // Verify project was added to projects list
        $this->assertArrayHasKey($projectPath, $this->state->projects);

        // Verify alias was registered
        $this->assertArrayHasKey($alias, $this->state->aliases);
        $this->assertSame($projectPath, $this->state->aliases[$alias]);
    }

    #[Test]
    public function it_can_add_project_without_becoming_current(): void
    {
        $projectPath = '/path/to/project';
        $alias = 'my-project';
        $configFile = 'context.yaml';
        $envFile = '.env.test';

        // Configure repository to expect save call
        $this->repository->expects($this->once())->method('save');

        $this->service->addProject(
            projectPath: $projectPath,
            alias: $alias,
            configFile: $configFile,
            envFile: $envFile,
        );

        // Verify project was added to projects list
        $this->assertArrayHasKey($projectPath, $this->state->projects);
        $projectInfo = $this->state->projects[$projectPath];
        $this->assertInstanceOf(ProjectDTO::class, $projectInfo);
        $this->assertSame($configFile, $projectInfo->configFile);
        $this->assertSame($envFile, $projectInfo->envFile);

        // Verify alias was registered
        $this->assertArrayHasKey($alias, $this->state->aliases);
        $this->assertSame($projectPath, $this->state->aliases[$alias]);

        // Current project should still be null
        $this->assertNull($this->state->currentProject);
    }

    #[Test]
    public function it_can_switch_to_existing_project(): void
    {
        $projectPath = '/path/to/project';

        // Add project first
        $this->state->projects[$projectPath] = new ProjectDTO(
            path: $projectPath,
            addedAt: \date('Y-m-d H:i:s'),
            configFile: 'context.yaml',
            envFile: '.env.test',
        );

        // Configure repository to expect save call
        $this->repository->expects($this->once())->method('save');

        // Switch to this project
        $result = $this->service->switchToProject($projectPath);

        $this->assertTrue($result);
        $this->assertNotNull($this->state->currentProject);
        $this->assertSame($projectPath, $this->state->currentProject->path);
        $this->assertSame('/path/to/project/context.yaml', $this->state->currentProject->getConfigFile());
        $this->assertSame('/path/to/project/.env.test', $this->state->currentProject->getEnvFile());
    }

    #[Test]
    public function it_returns_false_when_switching_to_nonexistent_project(): void
    {
        // Try to switch to a project that doesn't exist in state
        $result = $this->service->switchToProject('/non/existent/path');

        $this->assertFalse($result);
    }

    #[Test]
    public function it_can_resolve_path_from_alias(): void
    {
        $projectPath = '/path/to/project';
        $alias = 'my-project';

        // Register alias
        $this->state->aliases[$alias] = $projectPath;

        $resolvedPath = $this->service->resolvePathOrAlias($alias);
        $this->assertSame($projectPath, $resolvedPath);

        // When passing actual path, it should return the same path
        $resolvedPath = $this->service->resolvePathOrAlias($projectPath);
        $this->assertSame($projectPath, $resolvedPath);
    }

    #[Test]
    public function it_can_get_aliases_for_path(): void
    {
        $projectPath = '/path/to/project';
        $alias1 = 'alias1';
        $alias2 = 'alias2';

        // Register aliases
        $this->state->aliases[$alias1] = $projectPath;
        $this->state->aliases[$alias2] = $projectPath;
        $this->state->aliases['other-alias'] = '/other/path';

        $aliases = $this->service->getAliasesForPath($projectPath);

        $this->assertCount(2, $aliases);
        $this->assertContains($alias1, $aliases);
        $this->assertContains($alias2, $aliases);
        $this->assertNotContains('other-alias', $aliases);
    }

    #[Test]
    public function it_updates_existing_project_when_adding_same_path(): void
    {
        $projectPath = '/path/to/project';

        // Add project first with initial config
        $this->state->projects[$projectPath] = new ProjectDTO(
            path: $projectPath,
            addedAt: '2023-01-01 12:00:00',
            configFile: 'initial-config.yaml',
            envFile: '.env.initial',
        );

        // Add project again with updated config
        $this->service->addProject(
            projectPath: $projectPath,
            configFile: 'updated-config.yaml',
            envFile: '.env.updated',
        );

        // Verify project was updated
        $this->assertArrayHasKey($projectPath, $this->state->projects);
        $projectInfo = $this->state->projects[$projectPath];

        // Added date should be preserved
        $this->assertSame('2023-01-01 12:00:00', $projectInfo->addedAt);

        // Config and env files should be updated
        $this->assertSame('updated-config.yaml', $projectInfo->configFile);
        $this->assertSame('.env.updated', $projectInfo->envFile);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(ProjectStateRepositoryInterface::class);
        $this->files = $this->createMock(FilesInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // Create initial empty state
        $this->state = new ProjectStateDTO();

        // Configure repository to return the state
        $this->repository->method('load')->willReturn($this->state);

        // Setup save method to capture the state for assertions
        $this->repository->method('save')->willReturnCallback(
            function (ProjectStateDTO $state) {
                $this->state = $state;
                return true;
            },
        );

        $this->service = new ProjectService(
            files: $this->files,
            repository: $this->repository,
        );
    }
}
