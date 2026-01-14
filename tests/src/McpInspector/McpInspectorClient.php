<?php

declare(strict_types=1);

namespace Tests\McpInspector;

/**
 * Wrapper for @modelcontextprotocol/inspector CLI.
 */
final readonly class McpInspectorClient
{
    public function __construct(
        private string $ctxBinary = './ctx',
        private ?string $configPath = null,
        private ?string $stateDir = null,
    ) {}

    /**
     * List all available tools.
     */
    public function listTools(): McpInspectorResult
    {
        return $this->execute('tools/list');
    }

    /**
     * Call a tool with arguments.
     *
     * @param string $toolName Tool identifier (e.g., 'file-read')
     * @param array<string, mixed> $args Tool arguments
     */
    public function callTool(string $toolName, array $args = []): McpInspectorResult
    {
        $toolArgs = [];
        foreach ($args as $key => $value) {
            if (\is_array($value) || \is_object($value)) {
                $toolArgs[] = \sprintf('%s=%s', $key, \json_encode($value));
            } elseif (\is_bool($value)) {
                $toolArgs[] = \sprintf('%s=%s', $key, $value ? 'true' : 'false');
            } else {
                $toolArgs[] = \sprintf('%s=%s', $key, (string) $value);
            }
        }

        return $this->execute('tools/call', [
            '--tool-name' => $toolName,
            '--tool-arg' => $toolArgs,
        ]);
    }

    /**
     * List all available resources.
     */
    public function listResources(): McpInspectorResult
    {
        return $this->execute('resources/list');
    }

    /**
     * Read a resource by URI.
     */
    public function readResource(string $uri): McpInspectorResult
    {
        return $this->execute('resources/read', [
            '--resource-uri' => $uri,
        ]);
    }

    /**
     * List all available prompts.
     */
    public function listPrompts(): McpInspectorResult
    {
        return $this->execute('prompts/list');
    }

    /**
     * Get a prompt by name.
     *
     * @param string $name Prompt name
     * @param array<string, string> $args Prompt arguments
     */
    public function getPrompt(string $name, array $args = []): McpInspectorResult
    {
        $options = ['--prompt-name' => $name];

        foreach ($args as $key => $value) {
            $options['--prompt-arg'][] = \sprintf('%s=%s', $key, $value);
        }

        return $this->execute('prompts/get', $options);
    }

    /**
     * Execute inspector CLI command.
     *
     * @param string $method MCP method (e.g., 'tools/list', 'tools/call')
     * @param array<string, mixed> $options CLI options
     */
    private function execute(string $method, array $options = []): McpInspectorResult
    {
        $command = $this->buildCommand($method, $options);

        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $process = \proc_open($command, $descriptors, $pipes);

        if (!\is_resource($process)) {
            return new McpInspectorResult(
                success: false,
                exitCode: -1,
                output: '',
                error: 'Failed to start process',
                command: $command,
            );
        }

        \fclose($pipes[0]); // Close stdin

        $stdout = \stream_get_contents($pipes[1]);
        $stderr = \stream_get_contents($pipes[2]);

        \fclose($pipes[1]);
        \fclose($pipes[2]);

        $exitCode = \proc_close($process);

        return new McpInspectorResult(
            success: $exitCode === 0,
            exitCode: $exitCode,
            output: $stdout ?: '',
            error: $stderr ?: null,
            command: $command,
        );
    }

    /**
     * Build the full CLI command string.
     *
     * @param string $method MCP method
     * @param array<string, mixed> $options CLI options
     */
    private function buildCommand(string $method, array $options): string
    {
        $parts = [
            'npx',
            '@modelcontextprotocol/inspector',
            '--cli',
            $this->ctxBinary,
            'server',
        ];

        if ($this->configPath !== null) {
            $parts[] = '-c';
            $parts[] = \escapeshellarg($this->configPath);
        }

        if ($this->stateDir !== null) {
            $parts[] = '-s';
            $parts[] = \escapeshellarg($this->stateDir);
        }

        $parts[] = '--method';
        $parts[] = \escapeshellarg($method);

        foreach ($options as $key => $value) {
            if (\is_array($value)) {
                foreach ($value as $v) {
                    $parts[] = $key;
                    $parts[] = \escapeshellarg((string) $v);
                }
            } else {
                $parts[] = $key;
                $parts[] = \escapeshellarg((string) $value);
            }
        }

        return \implode(' ', $parts);
    }
}
