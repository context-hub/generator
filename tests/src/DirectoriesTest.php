<?php

declare(strict_types=1);

namespace Tests;

use Butschster\ContextGenerator\Directories;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Directories::class)]
final class DirectoriesTest extends TestCase
{
    #[Test]
    public function it_should_create_instance_with_valid_paths(): void
    {
        $dirs = new Directories(
            rootPath: '/test/root',
            outputPath: '/test/output',
            configPath: '/test/config',
            jsonSchemaPath: '/test/schema',
            envFilePath: '/test/env',
        );

        $this->assertSame('/test/root', $dirs->rootPath);
        $this->assertSame('/test/output', $dirs->outputPath);
        $this->assertSame('/test/config', $dirs->configPath);
        $this->assertSame('/test/schema', $dirs->jsonSchemaPath);
        $this->assertSame('/test/env', $dirs->envFilePath);
    }

    #[Test]
    public function it_should_create_instance_with_null_env_file_path(): void
    {
        $dirs = new Directories(
            rootPath: '/test/root',
            outputPath: '/test/output',
            configPath: '/test/config',
            jsonSchemaPath: '/test/schema',
        );

        $this->assertNull($dirs->envFilePath);
    }

    #[Test]
    public function it_should_throw_exception_for_empty_root_path(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Root path cannot be empty');

        new Directories(
            rootPath: '',
            outputPath: '/test/output',
            configPath: '/test/config',
            jsonSchemaPath: '/test/schema',
        );
    }

    #[Test]
    public function it_should_throw_exception_for_empty_output_path(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Output path cannot be empty');

        new Directories(
            rootPath: '/test/root',
            outputPath: '',
            configPath: '/test/config',
            jsonSchemaPath: '/test/schema',
        );
    }

    #[Test]
    public function it_should_throw_exception_for_empty_config_path(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Config path cannot be empty');

        new Directories(
            rootPath: '/test/root',
            outputPath: '/test/output',
            configPath: '',
            jsonSchemaPath: '/test/schema',
        );
    }

    #[Test]
    public function it_should_throw_exception_for_empty_json_schema_path(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('JSON schema path cannot be empty');

        new Directories(
            rootPath: '/test/root',
            outputPath: '/test/output',
            configPath: '/test/config',
            jsonSchemaPath: '',
        );
    }

    #[Test]
    public function it_should_create_new_instance_with_updated_root_path(): void
    {
        $dirs = new Directories(
            rootPath: '/test/root',
            outputPath: '/test/output',
            configPath: '/test/config',
            jsonSchemaPath: '/test/schema',
        );

        $newDirs = $dirs->withRootPath('/new/root');

        // Verify original instance is unchanged
        $this->assertSame('/test/root', $dirs->rootPath);

        // Verify new instance has updated root path
        $this->assertSame('/new/root', $newDirs->rootPath);
        $this->assertSame('/test/output', $newDirs->outputPath);
        $this->assertSame('/test/config', $newDirs->configPath);
        $this->assertSame('/test/schema', $newDirs->jsonSchemaPath);
    }

    #[Test]
    public function it_should_return_same_instance_when_root_path_is_null(): void
    {
        $dirs = new Directories(
            rootPath: '/test/root',
            outputPath: '/test/output',
            configPath: '/test/config',
            jsonSchemaPath: '/test/schema',
        );

        $newDirs = $dirs->withRootPath(null);

        $this->assertSame($dirs, $newDirs);
    }

    #[Test]
    public function it_should_create_new_instance_with_updated_config_path(): void
    {
        $dirs = new Directories(
            rootPath: '/test/root',
            outputPath: '/test/output',
            configPath: '/test/config',
            jsonSchemaPath: '/test/schema',
        );

        $newDirs = $dirs->withConfigPath('/new/config');

        // Verify original instance is unchanged
        $this->assertSame('/test/config', $dirs->configPath);

        // Verify new instance has updated config path
        $this->assertSame('/test/root', $newDirs->rootPath);
        $this->assertSame('/test/output', $newDirs->outputPath);
        $this->assertSame('/new/config', $newDirs->configPath);
        $this->assertSame('/test/schema', $newDirs->jsonSchemaPath);
    }

    #[Test]
    public function it_should_return_same_instance_when_config_path_is_null(): void
    {
        $dirs = new Directories(
            rootPath: '/test/root',
            outputPath: '/test/output',
            configPath: '/test/config',
            jsonSchemaPath: '/test/schema',
        );

        $newDirs = $dirs->withConfigPath(null);

        $this->assertSame($dirs, $newDirs);
    }

    #[Test]
    public function it_should_create_new_instance_with_env_file(): void
    {
        $dirs = new Directories(
            rootPath: '/test/root',
            outputPath: '/test/output',
            configPath: '/test/config',
            jsonSchemaPath: '/test/schema',
        );

        $newDirs = $dirs->withEnvFile('.env.test');

        // Verify original instance is unchanged
        $this->assertNull($dirs->envFilePath);

        // Verify new instance has updated env file path
        $this->assertSame('/test/root/.env.test', $newDirs->envFilePath);
    }

    #[Test]
    public function it_should_return_same_instance_when_env_file_is_null(): void
    {
        $dirs = new Directories(
            rootPath: '/test/root',
            outputPath: '/test/output',
            configPath: '/test/config',
            jsonSchemaPath: '/test/schema',
        );

        $newDirs = $dirs->withEnvFile(null);

        $this->assertSame($dirs, $newDirs);
    }

    #[Test]
    public function it_should_get_file_path_relative_to_root(): void
    {
        $dirs = new Directories(
            rootPath: '/test/root',
            outputPath: '/test/output',
            configPath: '/test/config',
            jsonSchemaPath: '/test/schema',
        );

        $this->assertSame('/test/root/file.txt', $dirs->getFilePath('file.txt'));
        $this->assertSame('/test/root/dir/file.txt', $dirs->getFilePath('dir/file.txt'));
        $this->assertSame('/test/root/file.txt', $dirs->getFilePath('/file.txt'));
    }

    #[Test]
    public function it_should_get_config_path_relative_to_config_dir(): void
    {
        $dirs = new Directories(
            rootPath: '/test/root',
            outputPath: '/test/output',
            configPath: '/test/config',
            jsonSchemaPath: '/test/schema',
        );

        $this->assertSame('/test/config/config.json', $dirs->getConfigPath('config.json'));
        $this->assertSame('/test/config/dir/config.json', $dirs->getConfigPath('dir/config.json'));
        $this->assertSame('/test/config/config.json', $dirs->getConfigPath('/config.json'));
    }

    #[Test]
    public function it_should_detect_absolute_paths(): void
    {
        $dirs = new Directories(
            rootPath: '/test/root',
            outputPath: '/test/output',
            configPath: '/test/config',
            jsonSchemaPath: '/test/schema',
        );

        $this->assertTrue($dirs->isAbsolutePath('/absolute/path'));
        $this->assertFalse($dirs->isAbsolutePath('relative/path'));
        $this->assertFalse($dirs->isAbsolutePath('./relative/path'));
        $this->assertFalse($dirs->isAbsolutePath('../relative/path'));
    }

    #[Test]
    public function it_should_resolve_paths_correctly(): void
    {
        $dirs = new Directories(
            rootPath: '/test/root',
            outputPath: '/test/output',
            configPath: '/test/config',
            jsonSchemaPath: '/test/schema',
        );

        // Absolute paths should remain unchanged
        $this->assertSame('/absolute/path', $dirs->resolvePath('/base/path', '/absolute/path'));

        // Relative paths should be resolved against the base path
        $this->assertSame('/base/path/relative/path', $dirs->resolvePath('/base/path', 'relative/path'));
    }

    #[Test]
    public function it_should_combine_paths_correctly(): void
    {
        $dirs = new Directories(
            rootPath: '/test/root',
            outputPath: '/test/output',
            configPath: '/test/config',
            jsonSchemaPath: '/test/schema',
        );

        // Should handle paths with and without trailing/leading slashes
        $this->assertSame('/base/path/file.txt', $dirs->combinePaths('/base/path', 'file.txt'));
        $this->assertSame('/base/path/file.txt', $dirs->combinePaths('/base/path/', 'file.txt'));
        $this->assertSame('/base/path/file.txt', $dirs->combinePaths('/base/path', '/file.txt'));
        $this->assertSame('/base/path/file.txt', $dirs->combinePaths('/base/path/', '/file.txt'));
    }

    #[Test]
    public function it_should_determine_root_path_from_config_path(): void
    {
        // Create a temporary directory for testing
        $tempDir = $this->createTempDir();
        $configDir = $tempDir . '/config';
        \mkdir($configDir);

        $dirs = new Directories(
            rootPath: '/test/root',
            outputPath: '/test/output',
            configPath: '/test/config',
            jsonSchemaPath: '/test/schema',
        );

        // Test with absolute config path to existing directory
        $newDirs = $dirs->determineRootPath($configDir);
        $this->assertSame($configDir, $newDirs->rootPath);
        $this->assertSame($configDir, $newDirs->configPath);

        // Test with absolute config path to existing file
        $configFile = $tempDir . '/config.json';
        \file_put_contents($configFile, '{}');
        $this->registerTempFile($configFile);

        $newDirs = $dirs->determineRootPath($configFile);
        $this->assertSame($tempDir, $newDirs->rootPath);
        $this->assertSame($configFile, $newDirs->configPath);
    }

    #[Test]
    public function it_should_resolve_relative_config_path_against_root_path(): void
    {
        // Create a temporary directory structure
        $tempDir = $this->createTempDir();
        $configDir = $tempDir . '/config';
        \mkdir($configDir);

        $dirs = new Directories(
            rootPath: $tempDir,
            outputPath: '/test/output',
            configPath: '/test/config',
            jsonSchemaPath: '/test/schema',
        );

        // Test with relative config path
        $newDirs = $dirs->determineRootPath('config');
        $this->assertSame($configDir, $newDirs->rootPath);
        $this->assertSame($configDir, $newDirs->configPath);
    }

    #[Test]
    public function it_should_return_same_instance_when_config_path_is_null_in_determine_root_path(): void
    {
        $dirs = new Directories(
            rootPath: '/test/root',
            outputPath: '/test/output',
            configPath: '/test/config',
            jsonSchemaPath: '/test/schema',
        );

        $newDirs = $dirs->determineRootPath(null);

        $this->assertSame($dirs, $newDirs);
    }

    #[Test]
    public function it_should_return_same_instance_when_inline_config_is_provided(): void
    {
        $dirs = new Directories(
            rootPath: '/test/root',
            outputPath: '/test/output',
            configPath: '/test/config',
            jsonSchemaPath: '/test/schema',
        );

        $newDirs = $dirs->determineRootPath('/path/to/config.json', '{"inline": "config"}');

        $this->assertSame($dirs, $newDirs);
    }

    #[Test]
    public function it_should_return_same_instance_when_config_dir_does_not_exist(): void
    {
        $dirs = new Directories(
            rootPath: '/test/root',
            outputPath: '/test/output',
            configPath: '/test/config',
            jsonSchemaPath: '/test/schema',
        );

        // Non-existent path
        $newDirs = $dirs->determineRootPath('/non/existent/path');

        $this->assertSame($dirs, $newDirs);
    }
}
