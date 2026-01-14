<?php

declare(strict_types=1);

namespace Tests\Feature\McpTools;

use Symfony\Component\Yaml\Yaml;
use Tests\Feature\Console\ConsoleTestCase;
use Tests\Feature\McpTools\Traits\InteractsWithMcpTools;

/**
 * Base test case for MCP tool tests.
 *
 * Provides a working directory and helper methods for executing
 * and asserting on MCP tool results.
 */
abstract class McpToolTestCase extends ConsoleTestCase
{
    use InteractsWithMcpTools;

    /**
     * Working directory for test files.
     */
    protected string $workDir;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->workDir = $this->createTempDir();
    }

    /**
     * Create a file in the work directory.
     *
     * @param string $relativePath Path relative to work directory
     * @param string $content File content
     * @return string Full path to created file
     */
    protected function createWorkFile(string $relativePath, string $content): string
    {
        $fullPath = $this->workDir . '/' . $relativePath;
        $dir = \dirname($fullPath);

        if (!\is_dir($dir)) {
            \mkdir($dir, 0777, true);
        }

        \file_put_contents($fullPath, $content);

        return $fullPath;
    }

    /**
     * Create a minimal context.yaml config in the work directory.
     *
     * @param array<string, mixed> $config Additional config to merge
     * @return string Path to the config file
     */
    protected function createWorkConfig(array $config = []): string
    {
        $defaultConfig = [
            'documents' => [],
        ];

        $mergedConfig = \array_merge($defaultConfig, $config);
        $yaml = Yaml::dump($mergedConfig, 4);

        return $this->createWorkFile('context.yaml', $yaml);
    }

    /**
     * Get a path relative to the work directory.
     */
    protected function workPath(string $relativePath): string
    {
        return $this->workDir . '/' . $relativePath;
    }

    /**
     * Read a file from the work directory.
     */
    protected function readWorkFile(string $relativePath): string
    {
        return \file_get_contents($this->workPath($relativePath));
    }

    /**
     * Check if a file exists in the work directory.
     */
    protected function workFileExists(string $relativePath): bool
    {
        return \file_exists($this->workPath($relativePath));
    }
}
