<?php

declare(strict_types=1);

namespace Tests\Feature\McpServer\Projects\DTO;

use Butschster\ContextGenerator\McpServer\Projects\DTO\CurrentProjectDTO;
use Butschster\ContextGenerator\McpServer\Projects\DTO\ProjectDTO;
use Butschster\ContextGenerator\McpServer\Projects\DTO\ProjectStateDTO;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ProjectStateDTOTest extends TestCase
{
    #[Test]
    public function it_can_be_created_empty(): void
    {
        $state = new ProjectStateDTO();

        $this->assertNull($state->currentProject);
        $this->assertEmpty($state->projects);
        $this->assertEmpty($state->aliases);
    }

    #[Test]
    public function it_can_be_created_with_data(): void
    {
        $currentProject = new CurrentProjectDTO(
            path: '/path/to/current',
        );

        $projects = [
            '/path/to/current' => new ProjectDTO(
                path: '/path/to/current',
                addedAt: '2023-01-01 12:00:00',
            ),
        ];

        $aliases = [
            'current' => '/path/to/current',
        ];

        $state = new ProjectStateDTO(
            currentProject: $currentProject,
            projects: $projects,
            aliases: $aliases,
        );

        $this->assertSame($currentProject, $state->currentProject);
        $this->assertSame($projects, $state->projects);
        $this->assertSame($aliases, $state->aliases);
    }

    #[Test]
    public function it_can_be_created_from_array(): void
    {
        $stateArray = [
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
        ];

        $state = ProjectStateDTO::fromArray($stateArray);

        // Verify current project
        $this->assertInstanceOf(CurrentProjectDTO::class, $state->currentProject);
        $this->assertSame('/path/to/current', $state->currentProject->path);

        // Verify projects
        $this->assertCount(2, $state->projects);
        $this->assertArrayHasKey('/path/to/current', $state->projects);
        $this->assertArrayHasKey('/path/to/other', $state->projects);

        $project = $state->projects['/path/to/current'];
        $this->assertInstanceOf(ProjectDTO::class, $project);
        $this->assertSame('2023-01-01 12:00:00', $project->addedAt);
        $this->assertSame('config.yaml', $project->configFile);
        $this->assertSame('.env', $project->envFile);

        // Verify aliases
        $this->assertCount(2, $state->aliases);
        $this->assertSame('/path/to/current', $state->aliases['current']);
        $this->assertSame('/path/to/other', $state->aliases['other']);
    }

    #[Test]
    public function it_can_get_aliases_for_path(): void
    {
        $state = new ProjectStateDTO(
            aliases: [
                'alias1' => '/path/one',
                'alias2' => '/path/one',
                'alias3' => '/path/two',
            ],
        );

        $aliases = $state->getAliasesForPath('/path/one');

        $this->assertCount(2, $aliases);
        $this->assertContains('alias1', $aliases);
        $this->assertContains('alias2', $aliases);
        $this->assertNotContains('alias3', $aliases);

        // Non-existent path should return empty array
        $this->assertEmpty($state->getAliasesForPath('/non/existent'));
    }

    #[Test]
    public function it_can_resolve_path_or_alias(): void
    {
        $state = new ProjectStateDTO(
            aliases: [
                'alias1' => '/path/one',
                'alias2' => '/path/two',
            ],
        );

        // Should resolve alias to path
        $this->assertSame('/path/one', $state->resolvePathOrAlias('alias1'));
        $this->assertSame('/path/two', $state->resolvePathOrAlias('alias2'));

        // When given a path that's not an alias, should return the same path
        $this->assertSame('/some/other/path', $state->resolvePathOrAlias('/some/other/path'));
    }

    #[Test]
    public function it_can_be_serialized_to_json(): void
    {
        $currentProject = new CurrentProjectDTO(
            path: '/path/to/current',
            configFile: 'config.yaml',
            envFile: '.env',
        );

        $projects = [
            '/path/to/current' => new ProjectDTO(
                path: '/path/to/current',
                addedAt: '2023-01-01 12:00:00',
                configFile: 'config.yaml',
                envFile: '.env',
            ),
        ];

        $aliases = [
            'current' => '/path/to/current',
        ];

        $state = new ProjectStateDTO(
            currentProject: $currentProject,
            projects: $projects,
            aliases: $aliases,
        );

        $json = \json_encode($state);
        $data = \json_decode($json, true);

        // Verify structure
        $this->assertArrayHasKey('current_project', $data);
        $this->assertArrayHasKey('projects', $data);
        $this->assertArrayHasKey('aliases', $data);

        // Verify current project
        $this->assertSame('/path/to/current', $data['current_project']['path']);
        $this->assertSame('config.yaml', $data['current_project']['config_file']);
        $this->assertSame('.env', $data['current_project']['env_file']);

        // Verify projects
        $this->assertArrayHasKey('/path/to/current', $data['projects']);
        $this->assertSame('2023-01-01 12:00:00', $data['projects']['/path/to/current']['added_at']);

        // Verify aliases
        $this->assertSame('/path/to/current', $data['aliases']['current']);
    }
}
