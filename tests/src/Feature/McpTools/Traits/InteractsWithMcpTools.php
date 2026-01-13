<?php

declare(strict_types=1);

namespace Tests\Feature\McpTools\Traits;

use Spiral\Console\Console;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Feature\McpTools\ToolExecutionResult;

/**
 * Trait providing helper methods for testing MCP tools.
 */
trait InteractsWithMcpTools
{
    /**
     * Execute an MCP tool with given arguments.
     *
     * @param string $toolId Tool identifier (e.g., 'ctx:file-read')
     * @param array<string, mixed> $args Tool arguments
     * @param string|null $configPath Path to config file
     */
    protected function executeTool(
        string $toolId,
        array $args = [],
        ?string $configPath = null,
    ): ToolExecutionResult {
        $console = $this->getConsole();

        $input = [
            'toolId' => $toolId,
            '--json' => \json_encode($args, JSON_THROW_ON_ERROR),
            '--output-json' => true,
        ];

        if ($configPath !== null) {
            $input['-c'] = $configPath;
        }

        $output = new BufferedOutput();
        $result = $console->run('tool:run', new ArrayInput($input), $output);

        return new ToolExecutionResult(
            exitCode: $result->getCode(),
            output: $output->fetch(),
            toolId: $toolId,
            args: $args,
        );
    }

    /**
     * Get JSON schema for a tool.
     *
     * @param string $toolId Tool identifier
     * @param string|null $configPath Path to config file
     * @return array<string, mixed>
     */
    protected function getToolSchema(string $toolId, ?string $configPath = null): array
    {
        $console = $this->getConsole();
        $output = new BufferedOutput();

        $input = [
            'toolId' => $toolId,
            '--json' => true,
        ];

        if ($configPath !== null) {
            $input['-c'] = $configPath;
        }

        $console->run('tool:schema', new ArrayInput($input), $output);

        $decoded = \json_decode($output->fetch(), true);

        return \json_last_error() === JSON_ERROR_NONE ? $decoded : [];
    }

    /**
     * Assert tool execution was successful.
     */
    protected function assertToolSuccess(ToolExecutionResult $result): self
    {
        $this->assertEquals(
            0,
            $result->exitCode,
            \sprintf("Tool '%s' failed with exit code %d: %s", $result->toolId, $result->exitCode, $result->output),
        );

        return $this;
    }

    /**
     * Assert tool execution failed.
     */
    protected function assertToolFailed(ToolExecutionResult $result): self
    {
        $this->assertNotEquals(
            0,
            $result->exitCode,
            \sprintf("Tool '%s' should have failed but succeeded with output: %s", $result->toolId, $result->output),
        );

        return $this;
    }

    /**
     * Assert tool output contains expected string.
     */
    protected function assertToolOutputContains(ToolExecutionResult $result, string $expected): self
    {
        $this->assertStringContainsString(
            $expected,
            $result->output,
            \sprintf("Tool '%s' output does not contain expected string: %s", $result->toolId, $expected),
        );

        return $this;
    }

    /**
     * Assert tool output does not contain a string.
     */
    protected function assertToolOutputNotContains(ToolExecutionResult $result, string $unexpected): self
    {
        $this->assertStringNotContainsString(
            $unexpected,
            $result->output,
            \sprintf("Tool '%s' output should not contain: %s", $result->toolId, $unexpected),
        );

        return $this;
    }

    /**
     * Assert tool output matches JSON structure.
     *
     * @param array<string, mixed> $expectedStructure Key-value pairs to check (null value means just check key exists)
     */
    protected function assertToolOutputJson(ToolExecutionResult $result, array $expectedStructure): self
    {
        $json = $result->getJsonOutput();

        $this->assertNotNull(
            $json,
            \sprintf("Tool '%s' output is not valid JSON: %s", $result->toolId, $result->output),
        );

        foreach ($expectedStructure as $key => $value) {
            $this->assertArrayHasKey(
                $key,
                $json,
                \sprintf("Tool '%s' JSON output missing key: %s", $result->toolId, $key),
            );

            if ($value !== null) {
                $this->assertEquals(
                    $value,
                    $json[$key],
                    \sprintf("Tool '%s' JSON output key '%s' has unexpected value", $result->toolId, $key),
                );
            }
        }

        return $this;
    }

    /**
     * Assert tool execution result indicates success in JSON.
     */
    protected function assertToolJsonSuccess(ToolExecutionResult $result): self
    {
        return $this->assertToolOutputJson($result, ['success' => true]);
    }

    /**
     * Assert tool execution result indicates failure in JSON.
     */
    protected function assertToolJsonFailed(ToolExecutionResult $result): self
    {
        $json = $result->getJsonOutput();

        $this->assertNotNull($json, "Tool output is not valid JSON");
        $this->assertTrue(
            (isset($json['success']) && $json['success'] === false) || isset($json['error']),
            "Tool JSON output should indicate failure",
        );

        return $this;
    }

    /**
     * Get the Console instance.
     * This method should be provided by the test class using this trait.
     */
    abstract protected function getConsole(): Console;
}
