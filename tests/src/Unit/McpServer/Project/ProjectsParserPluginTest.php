<?php

declare(strict_types=1);

namespace Tests\Unit\McpServer\Project;

use Butschster\ContextGenerator\McpServer\Project\Exception\ProjectPathException;
use Butschster\ContextGenerator\McpServer\Project\ProjectPathResolverInterface;
use Butschster\ContextGenerator\McpServer\Project\ProjectsParserPlugin;
use Butschster\ContextGenerator\McpServer\Project\ProjectWhitelistRegistry;
use Butschster\ContextGenerator\McpServer\Projects\ProjectServiceInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProjectsParserPluginTest extends TestCase
{
    private ProjectWhitelistRegistry $registry;
    private ProjectServiceInterface $projectService;
    private ProjectPathResolverInterface $pathResolver;
    private ProjectsParserPlugin $plugin;

    protected function setUp(): void
    {
        $this->registry = new ProjectWhitelistRegistry();
        $this->projectService = $this->createMock(ProjectServiceInterface::class);
        $this->pathResolver = $this->createMock(ProjectPathResolverInterface::class);

        $this->plugin = new ProjectsParserPlugin(
            registry: $this->registry,
            projectService: $this->projectService,
            pathResolver: $this->pathResolver,
        );
    }

    #[Test]
    public function it_returns_projects_config_key(): void
    {
        $this->assertEquals('projects', $this->plugin->getConfigKey());
    }

    #[Test]
    public function it_supports_config_with_projects_array(): void
    {
        $this->assertTrue($this->plugin->supports(['projects' => []]));
        $this->assertTrue($this->plugin->supports(['projects' => [['name' => 'test']]]));
    }

    #[Test]
    public function it_does_not_support_config_without_projects(): void
    {
        $this->assertFalse($this->plugin->supports([]));
        $this->assertFalse($this->plugin->supports(['other' => 'value']));
    }

    #[Test]
    public function it_does_not_support_non_array_projects(): void
    {
        $this->assertFalse($this->plugin->supports(['projects' => 'not-array']));
    }

    #[Test]
    public function it_registers_path_based_project(): void
    {
        $this->pathResolver
            ->method('resolve')
            ->with('../shared/lib', '/context/dir')
            ->willReturn('/absolute/shared/lib');

        $this->projectService
            ->method('getAliases')
            ->willReturn([]);

        $config = [
            'projects' => [
                [
                    'name' => 'shared-lib',
                    'description' => 'Shared library',
                    'path' => '../shared/lib',
                ],
            ],
        ];

        $this->plugin->parse($config, '/context/dir');

        $projects = $this->registry->getProjects();
        $this->assertCount(1, $projects);
        $this->assertEquals('shared-lib', $projects[0]->name);
        $this->assertEquals('Shared library', $projects[0]->description);
        $this->assertEquals('../shared/lib', $projects[0]->path);
        $this->assertEquals('/absolute/shared/lib', $projects[0]->resolvedPath);
        $this->assertTrue($projects[0]->isPathBased());
    }

    #[Test]
    public function it_registers_alias_based_project(): void
    {
        $this->projectService
            ->method('getAliases')
            ->willReturn(['ctx-mcp-server' => '/global/mcp-server']);

        $config = [
            'projects' => [
                [
                    'name' => 'ctx-mcp-server',
                    'description' => 'MCP Server',
                ],
            ],
        ];

        $this->plugin->parse($config, '/context/dir');

        $projects = $this->registry->getProjects();
        $this->assertCount(1, $projects);
        $this->assertEquals('ctx-mcp-server', $projects[0]->name);
        $this->assertEquals('/global/mcp-server', $projects[0]->resolvedPath);
        $this->assertFalse($projects[0]->isPathBased());
    }

    #[Test]
    public function it_skips_alias_not_found_in_global_registry(): void
    {
        $this->projectService
            ->method('getAliases')
            ->willReturn([]);

        $config = [
            'projects' => [
                [
                    'name' => 'unknown-alias',
                ],
            ],
        ];

        $this->plugin->parse($config, '/context/dir');

        $this->assertTrue($this->registry->isEmpty());
    }

    #[Test]
    public function it_gives_path_based_priority_over_alias(): void
    {
        // Path resolver returns same path as the global alias
        $this->pathResolver
            ->method('resolve')
            ->with('./local-project', '/context/dir')
            ->willReturn('/shared/project');

        // Global alias also points to /shared/project
        $this->projectService
            ->method('getAliases')
            ->willReturn(['global-project' => '/shared/project']);

        $config = [
            'projects' => [
                // Path-based (should win)
                [
                    'name' => 'local-project',
                    'path' => './local-project',
                ],
                // Alias-based pointing to same path (should be skipped)
                [
                    'name' => 'global-project',
                ],
            ],
        ];

        $this->plugin->parse($config, '/context/dir');

        $projects = $this->registry->getProjects();
        $this->assertCount(1, $projects);
        $this->assertEquals('local-project', $projects[0]->name);
        $this->assertTrue($projects[0]->isPathBased());
    }

    #[Test]
    public function it_handles_invalid_path_gracefully(): void
    {
        $this->pathResolver
            ->method('resolve')
            ->willThrowException(ProjectPathException::notFound('/invalid/path'));

        $this->projectService
            ->method('getAliases')
            ->willReturn([]);

        $config = [
            'projects' => [
                [
                    'name' => 'invalid-project',
                    'path' => '/invalid/path',
                ],
            ],
        ];

        $this->plugin->parse($config, '/context/dir');

        // Project should be skipped, not cause error
        $this->assertTrue($this->registry->isEmpty());
    }

    #[Test]
    public function it_registers_mixed_path_and_alias_projects(): void
    {
        $this->pathResolver
            ->method('resolve')
            ->with('../lib', '/context/dir')
            ->willReturn('/absolute/lib');

        $this->projectService
            ->method('getAliases')
            ->willReturn(['mcp-server' => '/global/mcp-server']);

        $config = [
            'projects' => [
                [
                    'name' => 'local-lib',
                    'path' => '../lib',
                ],
                [
                    'name' => 'mcp-server',
                ],
            ],
        ];

        $this->plugin->parse($config, '/context/dir');

        $projects = $this->registry->getProjects();
        $this->assertCount(2, $projects);

        $names = \array_map(static fn($p) => $p->name, $projects);
        $this->assertContains('local-lib', $names);
        $this->assertContains('mcp-server', $names);
    }

    #[Test]
    public function it_skips_duplicate_paths_in_yaml(): void
    {
        $this->pathResolver
            ->method('resolve')
            ->willReturn('/same/path');

        $this->projectService
            ->method('getAliases')
            ->willReturn([]);

        $config = [
            'projects' => [
                [
                    'name' => 'first-project',
                    'path' => './project',
                ],
                [
                    'name' => 'second-project',
                    'path' => '../other/../project', // Resolves to same path
                ],
            ],
        ];

        $this->plugin->parse($config, '/context/dir');

        $projects = $this->registry->getProjects();
        $this->assertCount(1, $projects);
        $this->assertEquals('first-project', $projects[0]->name);
    }

    #[Test]
    public function it_skips_project_with_missing_name(): void
    {
        $this->projectService
            ->method('getAliases')
            ->willReturn([]);

        $config = [
            'projects' => [
                [
                    'description' => 'No name provided',
                    'path' => '../lib',
                ],
            ],
        ];

        $this->plugin->parse($config, '/context/dir');

        $this->assertTrue($this->registry->isEmpty());
    }

    #[Test]
    public function it_skips_non_array_project_data(): void
    {
        $this->projectService
            ->method('getAliases')
            ->willReturn([]);

        $config = [
            'projects' => [
                'not-an-array',
                123,
                null,
            ],
        ];

        $this->plugin->parse($config, '/context/dir');

        $this->assertTrue($this->registry->isEmpty());
    }

    #[Test]
    public function it_returns_null_when_config_not_supported(): void
    {
        $result = $this->plugin->parse(['other' => 'data'], '/context/dir');

        $this->assertNull($result);
        $this->assertTrue($this->registry->isEmpty());
    }

    #[Test]
    public function it_does_not_modify_config_in_update(): void
    {
        $config = ['projects' => [['name' => 'test']]];

        $result = $this->plugin->updateConfig($config, '/context/dir');

        $this->assertEquals($config, $result);
    }
}
