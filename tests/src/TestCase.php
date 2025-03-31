<?php

declare(strict_types=1);

namespace Tests;

use Butschster\ContextGenerator\Directories;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base TestCase for all tests
 *
 * Provides common functionality for tests including:
 * - Helper methods for fixture loading
 * - Common test setup and teardown
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * @var array<string> Temporary files to clean up
     */
    private array $tempFiles = [];

    /**
     * @var array<string> Temporary directories to clean up
     */
    private array $tempDirs = [];

    /**
     * Clean up temporary files and directories
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up temp files
        foreach ($this->tempFiles as $file) {
            if (\file_exists($file)) {
                \unlink($file);
            }
        }

        // Clean up temp directories
        foreach ($this->tempDirs as $dir) {
            $this->removeDirectory($dir);
        }
    }

    protected function createDirectories(string $rootPath = '/test'): Directories
    {
        return new Directories(
            rootPath: $rootPath,
            outputPath: $rootPath . '/output',
            configPath: $rootPath . '/config',
            jsonSchemaPath: $rootPath . '/schema.json',
        );
    }

    /**
     * Get the path to the fixtures directory
     */
    protected function getFixturesDir(string $subdirectory = ''): string
    {
        $basePath = \dirname(__DIR__) . '/fixtures';

        if ($subdirectory) {
            return $basePath . '/' . $subdirectory;
        }

        return $basePath;
    }

    /**
     * Create a temporary file with content
     *
     * @param string $content Content to write to the file
     * @param string $extension File extension (default: .txt)
     * @return string Path to the temporary file
     */
    protected function createTempFile(string $content, string $extension = '.txt'): string
    {
        $tempFile = \tempnam(\sys_get_temp_dir(), 'test_') . $extension;
        \file_put_contents($tempFile, $content);

        // Register for cleanup
        $this->registerTempFile($tempFile);

        return $tempFile;
    }

    /**
     * Register a temporary file for cleanup
     */
    protected function registerTempFile(string $filePath): void
    {
        $this->tempFiles[] = $filePath;
    }

    /**
     * Create a temporary directory
     *
     * @return string Path to the temporary directory
     */
    protected function createTempDir(): string
    {
        $tempDir = \sys_get_temp_dir() . '/test_' . \uniqid();
        \mkdir($tempDir, 0777, true);

        // Register for cleanup
        $this->registerTempDir($tempDir);

        return $tempDir;
    }

    /**
     * Register a temporary directory for cleanup
     */
    protected function registerTempDir(string $dirPath): void
    {
        $this->tempDirs[] = $dirPath;
    }

    /**
     * Recursively remove a directory and all its contents
     */
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
