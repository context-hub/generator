<?php

declare(strict_types=1);

namespace Tests\McpInspector;

use PHPUnit\Framework\TestCase;

/**
 * Base test case for MCP Inspector tests.
 */
abstract class McpInspectorTestCase extends TestCase
{
    protected McpInspectorClient $inspector;
    protected string $workDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Check if npx is available
        $this->assertNpxAvailable();

        $this->workDir = $this->createTempDir();
        $this->inspector = $this->createInspector();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->workDir) && \is_dir($this->workDir)) {
            $this->removeDir($this->workDir);
        }
    }

    /**
     * Create inspector client instance.
     */
    protected function createInspector(?string $configPath = null, ?string $stateDir = null): McpInspectorClient
    {
        return new McpInspectorClient(
            ctxBinary: $this->getCtxBinary(),
            configPath: $configPath ?? $this->workDir,
            stateDir: $stateDir,
        );
    }

    /**
     * Get path to CTX binary.
     */
    protected function getCtxBinary(): string
    {
        // Adjust path based on your project structure
        return \dirname(__DIR__, 3) . '/ctx';
    }

    protected function vendorDir(string $path = ''): string
    {
        return \dirname(__DIR__, 3) . '/vendor/' . $path;
    }

    /**
     * Create a temporary directory for tests.
     */
    protected function createTempDir(): string
    {
        $dir = \sys_get_temp_dir() . '/mcp-test-' . \uniqid();
        \mkdir($dir, 0777, true);

        // Create minimal context.yaml for CTX
        \file_put_contents($dir . '/context.yaml', "documents: []\n");

        return $dir;
    }

    /**
     * Remove directory recursively.
     */
    protected function removeDir(string $dir): void
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
                $this->removeDir($path);
            } else {
                \unlink($path);
            }
        }

        \rmdir($dir);
    }

    /**
     * Assert npx is available and MCP Inspector works.
     */
    protected function assertNpxAvailable(): void
    {
        $output = [];
        $code = 0;
        \exec('which npx 2>/dev/null || where npx 2>NUL', $output, $code);

        if ($code !== 0) {
            $this->markTestSkipped('npx is not available. Install Node.js to run MCP Inspector tests.');
        }

        // Check if MCP Inspector can run (Node.js version compatibility)
        $testOutput = [];
        \exec('npx @modelcontextprotocol/inspector --help 2>&1', $testOutput, $testCode);
        $outputStr = \implode("\n", $testOutput);

        if (\str_contains($outputStr, 'import attribute of type "json"')) {
            $this->markTestSkipped('MCP Inspector requires Node.js 20+ for JSON module imports.');
        }
    }

    /**
     * Create a test file in work directory.
     */
    protected function createFile(string $relativePath, string $content): string
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
     * Assert inspector result was successful.
     */
    protected function assertInspectorSuccess(McpInspectorResult $result, string $message = ''): void
    {
        $this->assertTrue(
            $result->success,
            $message ?: "Inspector command failed.\nCommand: {$result->command}\nError: {$result->error}\nOutput: {$result->output}",
        );
    }

    /**
     * Assert inspector result failed.
     */
    protected function assertInspectorFailed(McpInspectorResult $result, string $message = ''): void
    {
        $this->assertFalse(
            $result->success,
            $message ?: "Inspector command should have failed.\nCommand: {$result->command}",
        );
    }

    /**
     * Assert tool returned an error.
     */
    protected function assertToolError(McpInspectorResult $result, string $message = ''): void
    {
        $this->assertTrue(
            $result->isToolError(),
            $message ?: "Tool should have returned an error.\nOutput: {$result->output}",
        );
    }

    /**
     * Assert output contains string.
     */
    protected function assertOutputContains(McpInspectorResult $result, string $needle): void
    {
        $this->assertStringContainsString(
            $needle,
            $result->output,
            "Output does not contain expected string: {$needle}",
        );
    }

    /**
     * Assert tool content contains string.
     */
    protected function assertContentContains(McpInspectorResult $result, string $needle): void
    {
        $content = $result->getContent() ?? $result->output;

        $this->assertStringContainsString(
            $needle,
            $content,
            "Content does not contain expected string: {$needle}",
        );
    }
}
