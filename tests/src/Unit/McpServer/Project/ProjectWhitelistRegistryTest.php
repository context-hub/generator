<?php

declare(strict_types=1);

namespace Tests\Unit\McpServer\Project;

use Butschster\ContextGenerator\McpServer\Project\ProjectConfig;
use Butschster\ContextGenerator\McpServer\Project\ProjectWhitelistRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProjectWhitelistRegistryTest extends TestCase
{
    private ProjectWhitelistRegistry $registry;

    #[Test]
    public function it_starts_empty(): void
    {
        $this->assertTrue($this->registry->isEmpty());
        $this->assertEmpty($this->registry->getProjects());
    }

    #[Test]
    public function it_registers_project(): void
    {
        $project = new ProjectConfig('test-project', 'Test description');

        $this->registry->register($project);

        $this->assertFalse($this->registry->isEmpty());
        $this->assertCount(1, $this->registry->getProjects());
        $this->assertTrue($this->registry->isAllowed('test-project'));
    }

    #[Test]
    public function it_checks_if_project_is_allowed(): void
    {
        $this->registry->register(new ProjectConfig('allowed-project'));

        $this->assertTrue($this->registry->isAllowed('allowed-project'));
        $this->assertFalse($this->registry->isAllowed('not-allowed'));
    }

    #[Test]
    public function it_returns_all_registered_projects(): void
    {
        $this->registry->register(new ProjectConfig('project-a', 'Description A'));
        $this->registry->register(new ProjectConfig('project-b', 'Description B'));

        $projects = $this->registry->getProjects();

        $this->assertCount(2, $projects);

        $names = \array_map(static fn($p) => $p->name, $projects);
        $this->assertContains('project-a', $names);
        $this->assertContains('project-b', $names);
    }

    #[Test]
    public function it_overwrites_duplicate_project_names(): void
    {
        $this->registry->register(new ProjectConfig('same-name', 'First'));
        $this->registry->register(new ProjectConfig('same-name', 'Second'));

        $projects = $this->registry->getProjects();

        $this->assertCount(1, $projects);
        $this->assertEquals('Second', $projects[0]->description);
    }

    #[Test]
    public function it_clears_all_projects(): void
    {
        $this->registry->register(new ProjectConfig('project-1'));
        $this->registry->register(new ProjectConfig('project-2'));

        $this->registry->clear();

        $this->assertTrue($this->registry->isEmpty());
        $this->assertEmpty($this->registry->getProjects());
        $this->assertFalse($this->registry->isAllowed('project-1'));
    }

    protected function setUp(): void
    {
        $this->registry = new ProjectWhitelistRegistry();
    }
}
