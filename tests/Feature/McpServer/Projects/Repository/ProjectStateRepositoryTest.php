<?php

declare(strict_types=1);

namespace Tests\Feature\McpServer\Projects\Repository;

use Butschster\ContextGenerator\McpServer\Projects\DTO\CurrentProjectDTO;
use Butschster\ContextGenerator\McpServer\Projects\DTO\ProjectDTO;
use Butschster\ContextGenerator\McpServer\Projects\DTO\ProjectStateDTO;
use Butschster\ContextGenerator\McpServer\Projects\Repository\ProjectStateRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Spiral\Files\FilesInterface;
use Tests\TestCase;

final class ProjectStateRepositoryTest extends TestCase
{
    private ProjectStateRepository $repository;
    private FilesInterface&MockObject $files;
    private LoggerInterface&MockObject $logger;
    private string $stateDirectory;
    private string $stateFilePath;

    #[Test]
    public function it_returns_empty_state_when_state_file_does_not_exist(): void
    {
        // Configure files mock to report the state file doesn't exist
        $this->files->method('exists')->with($this->stateFilePath)->willReturn(false);

        $state = $this->repository->load();

        $this->assertInstanceOf(ProjectStateDTO::class, $state);
        $this->assertNull($state->currentProject);
        $this->assertEmpty($state->projects);
        $this->assertEmpty($state->aliases);
    }

    #[Test]
    public function it_loads_state_from_file(): void
    {
        $stateJson = \json_encode([
            'current_project' => [
                'path' => '/path/to/current',
                'config_file' => 'config.yaml',
                'env_file' => '.env',
            ],
            'projects' => [
                '/path/to/current' => [
                    'added_at' => '2023-01-01 12:00:00',
                    'config_file' => 'config.yaml',
                    'env_file' => '.env',
                ],
                '/path/to/other' => [
                    'added_at' => '2023-01-02 12:00:00',
                    'config_file' => null,
                    'env_file' => null,
                ],
            ],
            'aliases' => [
                'current' => '/path/to/current',
                'other' => '/path/to/other',
            ],
        ]);

        // Configure files mock to report the state file exists and return content
        $this->files->method('exists')->with($this->stateFilePath)->willReturn(true);
        $this->files->method('read')->with($this->stateFilePath)->willReturn($stateJson);

        $state = $this->repository->load();

        // Verify current project
        $this->assertInstanceOf(CurrentProjectDTO::class, $state->currentProject);
        $this->assertSame('/path/to/current', $state->currentProject->path);
        $this->assertSame('/path/to/current/config.yaml', $state->currentProject->getConfigFile());
        $this->assertSame('/path/to/current/.env', $state->currentProject->getEnvFile());

        // Verify projects
        $this->assertCount(2, $state->projects);
        $this->assertArrayHasKey('/path/to/current', $state->projects);
        $this->assertArrayHasKey('/path/to/other', $state->projects);

        $currentProject = $state->projects['/path/to/current'];
        $this->assertInstanceOf(ProjectDTO::class, $currentProject);
        $this->assertSame('2023-01-01 12:00:00', $currentProject->addedAt);
        $this->assertSame('config.yaml', $currentProject->configFile);
        $this->assertSame('.env', $currentProject->envFile);

        // Verify aliases
        $this->assertCount(2, $state->aliases);
        $this->assertArrayHasKey('current', $state->aliases);
        $this->assertArrayHasKey('other', $state->aliases);
        $this->assertSame('/path/to/current', $state->aliases['current']);
        $this->assertSame('/path/to/other', $state->aliases['other']);
    }

    #[Test]
    public function it_returns_empty_state_on_invalid_json(): void
    {
        // Configure files mock to report the state file exists but return invalid JSON
        $this->files->method('exists')->with($this->stateFilePath)->willReturn(true);
        $this->files->method('read')->with($this->stateFilePath)->willReturn('invalid json');

        // Logger should receive an error message
        $this->logger->expects($this->once())->method('error');

        $state = $this->repository->load();

        // Should return empty state on error
        $this->assertInstanceOf(ProjectStateDTO::class, $state);
        $this->assertNull($state->currentProject);
        $this->assertEmpty($state->projects);
        $this->assertEmpty($state->aliases);
    }

    #[Test]
    public function it_saves_state_to_file(): void
    {
        $state = new ProjectStateDTO(
            currentProject: new CurrentProjectDTO(
                path: '/path/to/project',
                configFile: 'config.yaml',
                envFile: '.env',
            ),
            projects: [
                '/path/to/project' => new ProjectDTO(
                    path: '/path/to/project',
                    addedAt: '2023-01-01 12:00:00',
                    configFile: 'config.yaml',
                    envFile: '.env',
                ),
            ],
            aliases: [
                'project' => '/path/to/project',
            ],
        );

        // Ensure directory exists
        $this->files
            ->expects($this->once())
            ->method('ensureDirectory')
            ->with($this->stateDirectory);

        // Should write to the state file
        $this->files
            ->expects($this->once())
            ->method('write')
            ->with(
                $this->stateFilePath,
                $this->callback(static function ($content) {
                    $data = \json_decode($content, true);
                    // Verify essential structure is correct
                    return isset($data['current_project'], $data['projects'], $data['aliases'])
                        && $data['current_project']['path'] === '/path/to/project'
                        && isset($data['projects']['/path/to/project'])
                        && $data['aliases']['project'] === '/path/to/project';
                }),
            );

        $result = $this->repository->save($state);
        $this->assertTrue($result);
    }

    #[Test]
    public function it_handles_save_errors(): void
    {
        $state = new ProjectStateDTO();

        // Make ensureDirectory throw an exception
        $this->files
            ->method('ensureDirectory')
            ->willThrowException(new \RuntimeException('Directory error'));

        // Logger should receive an error message
        $this->logger->expects($this->once())->method('error');

        $result = $this->repository->save($state);
        $this->assertFalse($result);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = $this->createMock(FilesInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->stateDirectory = '/path/to/state';
        $this->stateFilePath = $this->stateDirectory . '/.project-state.json';

        $this->repository = new ProjectStateRepository(
            files: $this->files,
            logger: $this->logger,
            stateDirectory: $this->stateDirectory,
        );
    }
}
