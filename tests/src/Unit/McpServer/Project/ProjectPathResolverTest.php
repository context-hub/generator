<?php

declare(strict_types=1);

namespace Tests\Unit\McpServer\Project;

use Butschster\ContextGenerator\McpServer\Project\Exception\ProjectPathException;
use Butschster\ContextGenerator\McpServer\Project\ProjectPathResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProjectPathResolverTest extends TestCase
{
    private ProjectPathResolver $resolver;
    private string $tempDir;

    #[Test]
    public function it_resolves_absolute_path(): void
    {
        $projectDir = $this->tempDir . '/project';
        \mkdir($projectDir, 0755, true);

        $result = $this->resolver->resolve($projectDir, '/some/context/dir');

        $this->assertEquals($projectDir, $result);
    }

    #[Test]
    public function it_resolves_relative_path(): void
    {
        $contextDir = $this->tempDir . '/context';
        $projectDir = $this->tempDir . '/context/shared-lib';
        \mkdir($contextDir, 0755, true);
        \mkdir($projectDir, 0755, true);

        $result = $this->resolver->resolve('shared-lib', $contextDir);

        $this->assertEquals($projectDir, $result);
    }

    #[Test]
    public function it_resolves_relative_path_with_parent_segments(): void
    {
        $contextDir = $this->tempDir . '/projects/main';
        $projectDir = $this->tempDir . '/shared/rag-tools';
        \mkdir($contextDir, 0755, true);
        \mkdir($projectDir, 0755, true);

        $result = $this->resolver->resolve('../../shared/rag-tools', $contextDir);

        $this->assertEquals($projectDir, $result);
    }

    #[Test]
    public function it_resolves_relative_path_with_dot_prefix(): void
    {
        $contextDir = $this->tempDir . '/context';
        $projectDir = $this->tempDir . '/context/lib';
        \mkdir($contextDir, 0755, true);
        \mkdir($projectDir, 0755, true);

        $result = $this->resolver->resolve('./lib', $contextDir);

        $this->assertEquals($projectDir, $result);
    }

    #[Test]
    public function it_throws_not_found_for_nonexistent_path(): void
    {
        $nonExistentPath = $this->tempDir . '/does-not-exist';

        $this->expectException(ProjectPathException::class);
        $this->expectExceptionMessage("Project path '{$nonExistentPath}' does not exist.");

        $this->resolver->resolve($nonExistentPath, $this->tempDir);
    }

    #[Test]
    public function it_throws_not_directory_for_file_path(): void
    {
        $filePath = $this->tempDir . '/some-file.txt';
        \file_put_contents($filePath, 'test content');

        $this->expectException(ProjectPathException::class);
        $this->expectExceptionMessage("Project path '{$filePath}' is not a directory.");

        $this->resolver->resolve($filePath, $this->tempDir);
    }

    #[Test]
    public function it_has_correct_exception_reason_for_not_found(): void
    {
        $nonExistentPath = $this->tempDir . '/missing';

        try {
            $this->resolver->resolve($nonExistentPath, $this->tempDir);
            $this->fail('Expected ProjectPathException was not thrown');
        } catch (ProjectPathException $e) {
            $this->assertEquals('not_found', $e->reason);
            $this->assertEquals($nonExistentPath, $e->path);
        }
    }

    #[Test]
    public function it_has_correct_exception_reason_for_not_directory(): void
    {
        $filePath = $this->tempDir . '/file.txt';
        \file_put_contents($filePath, 'content');

        try {
            $this->resolver->resolve($filePath, $this->tempDir);
            $this->fail('Expected ProjectPathException was not thrown');
        } catch (ProjectPathException $e) {
            $this->assertEquals('not_directory', $e->reason);
            $this->assertEquals($filePath, $e->path);
        }
    }

    #[Test]
    public function it_resolves_nested_relative_path(): void
    {
        $contextDir = $this->tempDir . '/app';
        $projectDir = $this->tempDir . '/app/packages/shared/utils';
        \mkdir($contextDir, 0755, true);
        \mkdir($projectDir, 0755, true);

        $result = $this->resolver->resolve('packages/shared/utils', $contextDir);

        $this->assertEquals($projectDir, $result);
    }

    #[Test]
    public function it_normalizes_paths_with_mixed_separators(): void
    {
        $contextDir = $this->tempDir . '/context';
        $projectDir = $this->tempDir . '/context/sub/project';
        \mkdir($contextDir, 0755, true);
        \mkdir($projectDir, 0755, true);

        // Using forward slashes regardless of OS
        $result = $this->resolver->resolve('sub/project', $contextDir);

        $this->assertEquals($projectDir, $result);
    }

    protected function setUp(): void
    {
        $this->resolver = new ProjectPathResolver();
        $this->tempDir = \sys_get_temp_dir() . '/project-path-resolver-test-' . \uniqid();
        \mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        $items = \scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (\is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                \unlink($path);
            }
        }

        \rmdir($dir);
    }
}
