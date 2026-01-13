<?php

declare(strict_types=1);

namespace Tests\Unit\McpServer\Project;

use Butschster\ContextGenerator\McpServer\Project\ProjectConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProjectConfigTest extends TestCase
{
    #[Test]
    public function it_creates_with_name_only(): void
    {
        $config = new ProjectConfig('my-project');

        $this->assertEquals('my-project', $config->name);
        $this->assertNull($config->description);
    }

    #[Test]
    public function it_creates_with_name_and_description(): void
    {
        $config = new ProjectConfig('my-project', 'A test project');

        $this->assertEquals('my-project', $config->name);
        $this->assertEquals('A test project', $config->description);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $config = ProjectConfig::fromArray([
            'name' => 'array-project',
            'description' => 'Created from array',
        ]);

        $this->assertNotNull($config);
        $this->assertEquals('array-project', $config->name);
        $this->assertEquals('Created from array', $config->description);
    }

    #[Test]
    public function it_returns_null_for_missing_name(): void
    {
        $config = ProjectConfig::fromArray([
            'description' => 'No name provided',
        ]);

        $this->assertNull($config);
    }

    #[Test]
    public function it_returns_null_for_empty_name(): void
    {
        $config = ProjectConfig::fromArray([
            'name' => '',
            'description' => 'Empty name',
        ]);

        $this->assertNull($config);
    }

    #[Test]
    public function it_creates_from_array_without_description(): void
    {
        $config = ProjectConfig::fromArray([
            'name' => 'no-desc-project',
        ]);

        $this->assertNotNull($config);
        $this->assertEquals('no-desc-project', $config->name);
        $this->assertNull($config->description);
    }

    #[Test]
    public function it_serializes_to_json_with_name_only(): void
    {
        $config = new ProjectConfig('json-project');

        $json = $config->jsonSerialize();

        $this->assertEquals(['name' => 'json-project'], $json);
        $this->assertArrayNotHasKey('description', $json);
    }

    #[Test]
    public function it_serializes_to_json_with_description(): void
    {
        $config = new ProjectConfig('json-project', 'Has description');

        $json = $config->jsonSerialize();

        $this->assertEquals([
            'name' => 'json-project',
            'description' => 'Has description',
        ], $json);
    }
}
