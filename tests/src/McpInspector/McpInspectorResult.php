<?php

declare(strict_types=1);

namespace Tests\McpInspector;

/**
 * Result of an MCP Inspector CLI execution.
 */
final readonly class McpInspectorResult
{
    public function __construct(
        public bool $success,
        public int $exitCode,
        public string $output,
        public ?string $error,
        public string $command,
    ) {}

    /**
     * Parse output as JSON.
     *
     * @return array<string, mixed>|null
     */
    public function json(): ?array
    {
        $decoded = \json_decode($this->output, true);

        if (\json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $decoded;
    }

    /**
     * Get tool result content (for tools/call responses).
     */
    public function getContent(): ?string
    {
        $json = $this->json();

        // MCP Inspector returns content in various formats
        return $json['content'][0]['text']
            ?? $json['content']
            ?? $json['text']
            ?? null;
    }

    /**
     * Check if result indicates an error from the tool.
     */
    public function isToolError(): bool
    {
        // If CLI itself failed, consider it an error
        if (!$this->success) {
            return true;
        }

        $json = $this->json();

        return ($json['isError'] ?? false) === true;
    }

    /**
     * Get list of tools (for tools/list responses).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTools(): array
    {
        $json = $this->json();

        return $json['tools'] ?? [];
    }

    /**
     * Get list of resources (for resources/list responses).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getResources(): array
    {
        $json = $this->json();

        return $json['resources'] ?? [];
    }

    /**
     * Get list of prompts (for prompts/list responses).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPrompts(): array
    {
        $json = $this->json();

        return $json['prompts'] ?? [];
    }
}
