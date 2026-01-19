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
        $this->assertNull($config->path);
        $this->assertNull($config->resolvedPath);
    }

    #[Test]
    public function it_creates_with_name_and_description(): void
    {
        $config = new ProjectConfig('my-project', 'A test project');

        $this->assertEquals('my-project', $config->name);
        $this->assertEquals('A test project', $config->description);
        $this->assertNull($config->path);
        $this->assertNull($config->resolvedPath);
    }

    #[Test]
    public function it_creates_with_all_properties(): void
    {
        $config = new ProjectConfig(
            name: 'my-project',
            description: 'A test project',
            path: '../shared/lib',
            resolvedPath: '/home/user/shared/lib',
        );

        $this->assertEquals('my-project', $config->name);
        $this->assertEquals('A test project', $config->description);
        $this->assertEquals('../shared/lib', $config->path);
        $this->assertEquals('/home/user/shared/lib', $config->resolvedPath);
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
        $this->assertNull($config->path);
        $this->assertNull($config->resolvedPath);
    }

    #[Test]
    public function it_creates_from_array_with_path(): void
    {
        $config = ProjectConfig::fromArray([
            'name' => 'path-project',
            'description' => 'Has a path',
            'path' => '../shared/rag-tools',
        ]);

        $this->assertNotNull($config);
        $this->assertEquals('path-project', $config->name);
        $this->assertEquals('Has a path', $config->description);
        $this->assertEquals('../shared/rag-tools', $config->path);
        $this->assertNull($config->resolvedPath);
    }

    #[Test]
    public function it_creates_from_array_with_resolved_path(): void
    {
        $config = ProjectConfig::fromArray(
            data: [
                'name' => 'resolved-project',
                'path' => '../lib',
            ],
            resolvedPath: '/absolute/path/to/lib',
        );

        $this->assertNotNull($config);
        $this->assertEquals('resolved-project', $config->name);
        $this->assertEquals('../lib', $config->path);
        $this->assertEquals('/absolute/path/to/lib', $config->resolvedPath);
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
    public function it_is_path_based_when_path_is_set(): void
    {
        $config = new ProjectConfig(
            name: 'path-project',
            path: '../shared/lib',
        );

        $this->assertTrue($config->isPathBased());
    }

    #[Test]
    public function it_is_not_path_based_when_path_is_null(): void
    {
        $config = new ProjectConfig(name: 'alias-project');

        $this->assertFalse($config->isPathBased());
    }

    #[Test]
    public function it_creates_copy_with_resolved_path(): void
    {
        $original = new ProjectConfig(
            name: 'my-project',
            description: 'Test',
            path: '../lib',
        );

        $withResolved = $original->withResolvedPath('/absolute/lib');

        // Original unchanged
        $this->assertNull($original->resolvedPath);

        // New instance has resolved path
        $this->assertEquals('/absolute/lib', $withResolved->resolvedPath);
        $this->assertEquals('my-project', $withResolved->name);
        $this->assertEquals('Test', $withResolved->description);
        $this->assertEquals('../lib', $withResolved->path);
    }

    #[Test]
    public function it_serializes_to_json_with_name_only(): void
    {
        $config = new ProjectConfig('json-project');

        $json = $config->jsonSerialize();

        $this->assertEquals(['name' => 'json-project'], $json);
        $this->assertArrayNotHasKey('description', $json);
        $this->assertArrayNotHasKey('path', $json);
        $this->assertArrayNotHasKey('resolvedPath', $json);
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

    #[Test]
    public function it_serializes_to_json_with_path(): void
    {
        $config = new ProjectConfig(
            name: 'json-project',
            path: '../shared/lib',
        );

        $json = $config->jsonSerialize();

        $this->assertEquals([
            'name' => 'json-project',
            'path' => '../shared/lib',
        ], $json);
    }

    #[Test]
    public function it_serializes_to_json_with_all_fields(): void
    {
        $config = new ProjectConfig(
            name: 'full-project',
            description: 'Full description',
            path: '../lib',
            resolvedPath: '/home/user/lib',
        );

        $json = $config->jsonSerialize();

        $this->assertEquals([
            'name' => 'full-project',
            'description' => 'Full description',
            'path' => '../lib',
            'resolvedPath' => '/home/user/lib',
        ], $json);
    }
}
